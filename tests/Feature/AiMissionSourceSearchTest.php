<?php

namespace Tests\Feature;

use App\Domain\Ai\Missions\MissionRunner;
use App\Domain\Ai\Missions\MissionSourceChunker;
use App\Domain\Ai\Missions\MissionSourceDigester;
use App\Domain\Ai\Missions\MissionSourceSearch;
use App\Domain\Ai\Missions\MissionSourceTools;
use App\Enums\AiMissionCardKind;
use App\Enums\AiMissionRoleType;
use App\Filament\Pages\AiMissionRoom;
use App\Models\AiAssistantProfile;
use App\Models\AiMission;
use App\Models\AiMissionCard;
use App\Models\AiMissionRole;
use App\Models\AiMissionSource;
use App\Models\AiMissionSourceChunk;
use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Searching a mission's files, citing the exact piece a finding quotes, and
 * limiting which member reads which file.
 */
class AiMissionSourceSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->admin = User::factory()->create(['name' => 'مدیر آزمایشی']);
        $this->admin->assignRole(Role::firstOrCreate(['name' => 'admin']));
        $this->actingAs($this->admin);
        AiProvider::factory()->active()->create();
    }

    private function sourceWithChunk(string $title, string $text, ?string $heading = null, ?AiMission $mission = null): AiMissionSourceChunk
    {
        $mission ??= AiMission::factory()->create(['created_by' => $this->admin->id]);
        $source = AiMissionSource::factory()->create(['ai_mission_id' => $mission->id, 'title' => $title, 'content' => $text]);

        return AiMissionSourceChunk::factory()->create(['ai_mission_source_id' => $source->id, 'position' => 1, 'heading' => $heading, 'content' => $text]);
    }

    /**
     * @return array{mission: AiMission, specialist: AiMissionRole, coordinator: AiMissionRole}
     */
    private function missionWithTeam(): array
    {
        $mission = AiMission::factory()->create(['created_by' => $this->admin->id, 'goal' => 'برنامهٔ هفتهٔ آینده']);
        $specialist = AiMissionRole::factory()->create([
            'ai_mission_id' => $mission->id,
            'ai_assistant_profile_id' => AiAssistantProfile::factory()->create(['created_by' => $this->admin->id])->id,
            'role' => AiMissionRoleType::Specialist,
        ]);
        $coordinator = AiMissionRole::factory()->create([
            'ai_mission_id' => $mission->id,
            'ai_assistant_profile_id' => AiAssistantProfile::factory()->create(['created_by' => $this->admin->id])->id,
            'role' => AiMissionRoleType::Coordinator,
        ]);

        return compact('mission', 'specialist', 'coordinator');
    }

    public function test_the_chunker_keeps_headings_repeats_sheet_headers_and_splits_long_text(): void
    {
        $chunker = app(MissionSourceChunker::class);

        $markdown = $chunker->chunk("# اهداف\nاشغال بیشتر\n# اصول\nقیمت پویا");
        $this->assertSame(['اهداف', 'اصول'], array_column($markdown, 'heading'));

        $sheet = "کاربرگ «اشغال»\nماه | نرخ\n".collect(range(1, 400))->map(fn (int $row) => "ماه {$row} | ".($row % 100))->implode("\n");
        $pieces = $chunker->chunk($sheet);
        $this->assertGreaterThan(1, count($pieces));

        foreach ($pieces as $piece) {
            $this->assertSame('اشغال', $piece['heading']);
            $this->assertStringStartsWith('ماه | نرخ', $piece['content']);
            $this->assertLessThanOrEqual(MissionSourceChunker::TARGET + 60, mb_strlen($piece['content']));
        }

        $long = collect(range(1, 200))->map(fn (int $n) => "این جملهٔ شمارهٔ {$n} دربارهٔ استراتژی هتل است.")->implode(' ');
        foreach ($chunker->chunk($long) as $piece) {
            $this->assertLessThanOrEqual(MissionSourceChunker::TARGET, mb_strlen($piece['content']));
        }
    }

    public function test_processing_a_file_cuts_it_into_pieces_that_can_be_cited(): void
    {
        Http::fake(['*/chat/completions' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => '{"summary":"x"}']]]])]);
        Storage::disk('local')->put('mission-sources/plan.md', "# هدف\nاشغال ۷۵ درصد\n# اصل\nقیمت پویا");
        $source = AiMissionSource::factory()->create(['user_id' => $this->admin->id, 'title' => 'برنامه', 'extension' => 'md', 'path' => 'mission-sources/plan.md', 'content' => null, 'content_hash' => null]);

        app(MissionSourceDigester::class)->process($source);

        $chunks = $source->chunks()->get();
        $this->assertCount(2, $chunks);
        $this->assertSame('«برنامه»، بخش 1 (هدف)', $chunks->first()->load('source')->citation());
    }

    public function test_search_ranks_by_meaning_across_persian_spellings_and_digits(): void
    {
        $occupancy = $this->sourceWithChunk('استراتژی', 'هدف سال آینده رسیدن به نرخ اشغالی ۷۵ درصد در ویلاهای شمال است.');
        $other = $this->sourceWithChunk('گزارش', 'هزینهٔ نظافت و برق ماهانه در جدول آمده است.');
        $search = app(MissionSourceSearch::class);

        $hits = $search->search([$occupancy->ai_mission_source_id, $other->ai_mission_source_id], 'اشغال ويلا 75');

        $this->assertSame($occupancy->id, $hits[0]['chunk']->id);
        $this->assertCount(1, $hits);
        $this->assertSame([], $search->search([$other->ai_mission_source_id], 'اشغال'));
        $this->assertSame([], $search->search([$occupancy->ai_mission_source_id], 'و در به'));
    }

    public function test_a_member_reads_only_the_files_the_owner_allowed(): void
    {
        ['mission' => $mission, 'specialist' => $specialist] = $this->missionWithTeam();
        $a = $this->sourceWithChunk('الف', 'نرخ اشغال', null, $mission)->source;
        $b = $this->sourceWithChunk('ب', 'درآمد ماهانه', null, $mission)->source;
        $tools = app(MissionSourceTools::class);

        $this->assertEqualsCanonicalizing([$a->id, $b->id], $tools->allowedSourceIds($mission, $specialist));

        $specialist->sources()->attach($a->id);
        $this->assertSame([$a->id], $tools->allowedSourceIds($mission, $specialist));
    }

    public function test_no_search_tool_is_offered_when_the_mission_has_no_files(): void
    {
        ['mission' => $mission, 'specialist' => $specialist] = $this->missionWithTeam();
        $citations = [];

        $this->assertSame([], app(MissionSourceTools::class)->for($mission, $specialist, $citations));
    }

    public function test_a_finding_that_copies_a_returned_citation_is_tied_to_that_piece(): void
    {
        ['mission' => $mission] = $this->missionWithTeam();
        $chunk = $this->sourceWithChunk('برنامهٔ راهبردی', 'هدف سال آینده رسیدن به نرخ اشغال ۷۵ درصد است.', null, $mission);
        $citation = $chunk->load('source')->citation();

        $toolResults = [];
        Http::fake(['*/chat/completions' => function (Request $request) use ($citation, &$toolResults) {
            $messages = $request->data()['messages'];

            if (str_contains($messages[0]['content'], 'نقش «هماهنگ‌کننده»')) {
                return Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => json_encode(['summary' => 'خلاصه', 'decisions' => []], JSON_UNESCAPED_UNICODE)]]]]);
            }

            if (end($messages)['role'] === 'tool') {
                $toolResults[] = end($messages)['content'];

                return Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => json_encode(['findings' => [['claim' => 'هدف اشغال ۷۵٪ است.', 'source' => $citation, 'confidence' => 'high']]], JSON_UNESCAPED_UNICODE)]]]]);
            }

            return Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => [['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'search_mission_sources', 'arguments' => json_encode(['query' => 'نرخ اشغال'], JSON_UNESCAPED_UNICODE)]]]]]]]);
        }]);

        $this->assertTrue(app(MissionRunner::class)->runRound($mission->fresh(), $this->admin)['ok']);

        $card = AiMissionCard::where('kind', AiMissionCardKind::Finding)->sole();
        $this->assertSame($chunk->id, $card->source_chunk_id);
        $this->assertSame($citation, $card->source);
        $this->assertFalse($card->unsourced);
        $this->assertStringContainsString('نرخ اشغال', $toolResults[0]);
        $this->assertStringContainsString('بخش 1', $toolResults[0]);
    }

    public function test_a_member_limited_to_one_file_never_gets_text_from_another(): void
    {
        ['mission' => $mission, 'specialist' => $specialist] = $this->missionWithTeam();
        $allowed = $this->sourceWithChunk('مجاز', 'نرخ اشغال', null, $mission);
        $secret = $this->sourceWithChunk('محرمانه', 'درآمد ماهانه ۹۰۰ میلیون', null, $mission);
        $specialist->sources()->attach($allowed->ai_mission_source_id);

        $toolResults = [];
        Http::fake(['*/chat/completions' => function (Request $request) use (&$toolResults) {
            $messages = $request->data()['messages'];

            if (! str_contains($messages[0]['content'], 'نقش «متخصص»')) {
                return Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => json_encode(['summary' => 'خلاصه', 'decisions' => []], JSON_UNESCAPED_UNICODE)]]]]);
            }

            if (end($messages)['role'] === 'tool') {
                $toolResults[] = end($messages)['content'];

                return Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => '{"findings":[]}']]]]);
            }

            return Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => [['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'search_mission_sources', 'arguments' => json_encode(['query' => 'درآمد ماهانه'], JSON_UNESCAPED_UNICODE)]]]]]]]);
        }]);

        app(MissionRunner::class)->runRound($mission->fresh(), $this->admin);

        $this->assertNotEmpty($toolResults);
        $this->assertStringNotContainsString('۹۰۰ میلیون', $toolResults[0]);
        $this->assertStringContainsString('چیزی پیدا نشد', $toolResults[0]);
    }

    public function test_the_owner_limits_a_member_and_ticking_everything_returns_to_the_default(): void
    {
        ['mission' => $mission, 'specialist' => $specialist] = $this->missionWithTeam();
        $a = $this->sourceWithChunk('الف', 'x', null, $mission)->source;
        $b = $this->sourceWithChunk('ب', 'y', null, $mission)->source;

        $page = Livewire::test(AiMissionRoom::class)->call('openMission', $mission->id);

        $page->call('toggleRoleSource', $specialist->id, $b->id);
        $this->assertSame([$a->id], $specialist->sources()->pluck('ai_mission_sources.id')->all());

        $page->call('toggleRoleSource', $specialist->id, $a->id);
        $this->assertSame([$a->id], $specialist->sources()->pluck('ai_mission_sources.id')->all(), 'the last file cannot be removed');

        $page->call('toggleRoleSource', $specialist->id, $b->id);
        $this->assertSame(0, $specialist->sources()->count());
    }

    public function test_deleting_a_file_also_takes_it_off_the_members(): void
    {
        ['mission' => $mission, 'specialist' => $specialist] = $this->missionWithTeam();
        $a = $this->sourceWithChunk('الف', 'x', null, $mission)->source;
        $b = $this->sourceWithChunk('ب', 'y', null, $mission)->source;
        $specialist->sources()->attach($a->id);

        Livewire::test(AiMissionRoom::class)->call('openMission', $mission->id)->call('deleteSource', $a->id);

        $this->assertSame(0, $specialist->sources()->count());
        $this->assertSame([$b->id], $mission->sources()->pluck('id')->all());
    }

    public function test_the_board_offers_the_quoted_text_of_a_cited_finding(): void
    {
        ['mission' => $mission, 'specialist' => $specialist] = $this->missionWithTeam();
        $chunk = $this->sourceWithChunk('برنامهٔ راهبردی', 'هدف: اشغال ۷۵ درصد تا پایان سال', null, $mission);
        AiMissionCard::create([
            'ai_mission_id' => $mission->id,
            'ai_assistant_profile_id' => $specialist->ai_assistant_profile_id,
            'role' => AiMissionRoleType::Specialist,
            'round' => 1,
            'kind' => AiMissionCardKind::Finding,
            'claim' => 'هدف اشغال ۷۵٪ است.',
            'source' => $chunk->load('source')->citation(),
            'source_chunk_id' => $chunk->id,
            'confidence' => 'high',
            'unsourced' => false,
        ]);

        Livewire::test(AiMissionRoom::class)
            ->call('openMission', $mission->id)
            ->assertSee('متن همین بخش از فایل را ببین')
            ->assertSee('اشغال ۷۵ درصد تا پایان سال');
    }
}
