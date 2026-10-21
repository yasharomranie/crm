<?php

namespace Tests\Feature;

use App\Domain\Ai\DocumentTextExtractor;
use App\Domain\Ai\Missions\MissionBrief;
use App\Domain\Ai\Missions\MissionRunner;
use App\Enums\AiMissionBriefStatus;
use App\Enums\AiMissionRoleType;
use App\Enums\AiMissionSourceKind;
use App\Enums\AiMissionSourceStatus;
use App\Enums\AiMissionStatus;
use App\Filament\Pages\AiMissionRoom;
use App\Models\AiAssistantProfile;
use App\Models\AiMission;
use App\Models\AiMissionRole;
use App\Models\AiMissionSource;
use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * A mission's source files: extracted and digested once into a library, then
 * merged into a strategy brief the owner approves before the team may run.
 */
class AiMissionSourcesTest extends TestCase
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function fakeJson(array $payload): void
    {
        Http::fake(['*/chat/completions' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => json_encode($payload, JSON_UNESCAPED_UNICODE)]]]])]);
    }

    private function digest(): array
    {
        return [
            'summary' => 'استراتژی سال آینده',
            'goals' => ['رسیدن به اشغال ۷۵٪'],
            'principles' => ['قیمت‌گذاری پویا'],
            'red_lines' => ['هیچ‌وقت زیر قیمت تمام‌شده نفروش'],
            'kpis' => ['اشغال ماهانه ۷۵٪'],
            'facts' => [],
            'conflicts' => [],
        ];
    }

    private function mission(): AiMission
    {
        return AiMission::factory()->create(['created_by' => $this->admin->id, 'goal' => 'برنامهٔ هفتهٔ آینده']);
    }

    private function missionWithTeam(): AiMission
    {
        $mission = $this->mission();

        foreach ([AiMissionRoleType::Specialist, AiMissionRoleType::Coordinator] as $type) {
            AiMissionRole::factory()->create([
                'ai_mission_id' => $mission->id,
                'ai_assistant_profile_id' => AiAssistantProfile::factory()->create(['created_by' => $this->admin->id])->id,
                'role' => $type,
            ]);
        }

        return $mission;
    }

    public function test_the_extractor_reads_csv_markdown_and_xlsx_with_labels_next_to_numbers(): void
    {
        $extractor = app(DocumentTextExtractor::class);

        $csv = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($csv, "\xEF\xBB\xBFماه;اشغال\nمهر;۷۸\n");
        $this->assertSame("ماه | اشغال\nمهر | ۷۸", $extractor->extract($csv, 'csv')['content']);

        $md = tempnam(sys_get_temp_dir(), 'md');
        file_put_contents($md, "# هدف\nاشغال بیشتر");
        $this->assertStringContainsString('اشغال بیشتر', $extractor->extract($md, 'md')['content']);

        $xlsx = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new \ZipArchive;
        $zip->open($xlsx, \ZipArchive::OVERWRITE);
        $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="اشغال" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="x" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/sharedStrings.xml', '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>ماه</t></si><si><t>نرخ اشغال</t></si><si><r><t>مهر</t></r></si></sst>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="s"><v>0</v></c><c r="C1" t="s"><v>1</v></c></row><row r="2"><c r="A2" t="s"><v>2</v></c><c r="C2"><v>78.5</v></c></row></sheetData></worksheet>');
        $zip->close();

        $this->assertSame("کاربرگ «اشغال»\nماه |  | نرخ اشغال\nمهر |  | 78.5", $extractor->extract($xlsx, 'xlsx')['content']);
    }

    public function test_uploading_files_stores_them_digests_them_and_attaches_them_to_the_open_mission(): void
    {
        $this->fakeJson($this->digest());
        $mission = $this->mission();

        Livewire::test(AiMissionRoom::class)
            ->call('openMission', $mission->id)
            ->set('sourceKind', 'policy')
            ->set('sourceUploads', [UploadedFile::fake()->createWithContent('strategy.txt', 'استراتژی ما اشغال ۷۵ درصد است.')])
            ->call('uploadSources')
            ->assertHasNoErrors();

        $source = AiMissionSource::sole();
        $this->assertSame('strategy', $source->title);
        $this->assertSame(AiMissionSourceStatus::Ready, $source->status);
        $this->assertSame(['رسیدن به اشغال ۷۵٪'], $source->digest['goals']);
        $this->assertSame(AiMissionSourceKind::Policy, $source->kind);
        $this->assertSame($this->admin->id, $source->user_id);
        Storage::disk('local')->assertExists($source->path);
        $this->assertTrue($mission->sources()->whereKey($source->id)->exists());
    }

    public function test_a_file_type_outside_the_list_is_rejected(): void
    {
        Livewire::test(AiMissionRoom::class)
            ->set('sourceUploads', [UploadedFile::fake()->createWithContent('virus.exe', 'MZ')])
            ->call('uploadSources')
            ->assertHasErrors('sourceUploads.0');

        $this->assertSame(0, AiMissionSource::count());
    }

    public function test_a_provider_failure_marks_the_file_failed_but_keeps_its_text_for_a_retry(): void
    {
        $failing = true;
        Http::fake(['*/chat/completions' => function () use (&$failing) {
            return $failing
                ? Http::response(['error' => ['message' => 'quota exceeded']], 429)
                : Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => json_encode($this->digest(), JSON_UNESCAPED_UNICODE)]]]]);
        }]);

        $component = Livewire::test(AiMissionRoom::class)
            ->set('sourceUploads', [UploadedFile::fake()->createWithContent('plan.csv', "ماه;اشغال\nمهر;۷۸\n")])
            ->call('uploadSources');

        $source = AiMissionSource::sole();
        $this->assertSame(AiMissionSourceStatus::Failed, $source->status);
        $this->assertStringContainsString('quota exceeded', $source->error);
        $this->assertSame("ماه | اشغال\nمهر | ۷۸", $source->content);

        $failing = false;
        $component->call('retrySource', $source->id);

        $this->assertSame(AiMissionSourceStatus::Ready, $source->fresh()->status);
    }

    public function test_one_source_becomes_the_brief_without_another_ai_call_and_waits_for_approval(): void
    {
        $mission = $this->mission();
        $source = AiMissionSource::factory()->create(['user_id' => $this->admin->id, 'digest' => $this->digest()]);
        $mission->sources()->attach($source);
        Http::fake();

        Livewire::test(AiMissionRoom::class)->call('openMission', $mission->id)->call('buildBrief');

        Http::assertNothingSent();
        $mission->refresh();
        $this->assertSame(AiMissionBriefStatus::Review, $mission->brief_status);
        $this->assertSame(['هیچ‌وقت زیر قیمت تمام‌شده نفروش'], $mission->brief['red_lines']);
        $this->assertNull($mission->brief_approved_at);
    }

    public function test_several_sources_are_merged_by_the_ai_with_binding_files_marked(): void
    {
        $mission = $this->mission();
        $mission->sources()->attach([
            AiMissionSource::factory()->create(['user_id' => $this->admin->id, 'title' => 'سیاست قیمت', 'kind' => AiMissionSourceKind::Policy])->id,
            AiMissionSource::factory()->create(['user_id' => $this->admin->id, 'title' => 'گزارش فروش', 'kind' => AiMissionSourceKind::Evidence])->id,
        ]);
        $seen = null;
        Http::fake(['*/chat/completions' => function (Request $request) use (&$seen) {
            $seen = $request->data()['messages'][1]['content'];

            return Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => json_encode(array_merge($this->digest(), ['conflicts' => ['اشغال هدف با گزارش نمی‌خواند']]), JSON_UNESCAPED_UNICODE)]]]]);
        }]);

        Livewire::test(AiMissionRoom::class)->call('openMission', $mission->id)->call('buildBrief');

        $this->assertStringContainsString('سیاست قیمت', $seen);
        $this->assertStringContainsString('"kind":"evidence"', $seen);
        $this->assertSame(['اشغال هدف با گزارش نمی‌خواند'], $mission->fresh()->brief['conflicts']);
    }

    public function test_a_source_that_is_not_ready_blocks_building_the_brief(): void
    {
        $mission = $this->mission();
        $mission->sources()->attach(AiMissionSource::factory()->create(['user_id' => $this->admin->id, 'status' => AiMissionSourceStatus::Failed, 'digest' => null])->id);

        Livewire::test(AiMissionRoom::class)->call('openMission', $mission->id)->call('buildBrief');

        $this->assertSame(AiMissionBriefStatus::None, $mission->fresh()->brief_status);
    }

    public function test_a_mission_with_sources_does_not_run_until_the_brief_is_approved_and_current(): void
    {
        $mission = $this->missionWithTeam();
        $source = AiMissionSource::factory()->create(['user_id' => $this->admin->id, 'digest' => $this->digest()]);
        $mission->sources()->attach($source);

        $result = app(MissionRunner::class)->start($mission, $this->admin);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('خلاصهٔ استراتژی', $result['error']);
        $this->assertSame(AiMissionStatus::Draft, $mission->fresh()->status);

        $page = Livewire::test(AiMissionRoom::class)->call('openMission', $mission->id)->call('buildBrief')->call('approveBrief');
        $this->assertSame(AiMissionBriefStatus::Approved, $mission->fresh()->brief_status);
        $this->assertTrue(app(MissionRunner::class)->start($mission->fresh(), $this->admin)['ok']);

        $mission->refresh()->update(['status' => AiMissionStatus::Draft]);
        $source->update(['kind' => AiMissionSourceKind::Evidence]);
        $blocked = app(MissionRunner::class)->start($mission->fresh(), $this->admin);
        $this->assertFalse($blocked['ok']);
        $this->assertStringContainsString('تغییر کرده', $blocked['error']);
        $this->assertNotNull($page);
    }

    public function test_a_mission_without_sources_runs_as_before(): void
    {
        $mission = $this->missionWithTeam();

        $this->assertTrue(app(MissionRunner::class)->start($mission, $this->admin)['ok']);
    }

    public function test_members_read_the_approved_brief_and_nothing_else_from_the_files(): void
    {
        $mission = $this->missionWithTeam();
        $source = AiMissionSource::factory()->create(['user_id' => $this->admin->id, 'digest' => $this->digest(), 'content' => 'متن خام خیلی محرمانه']);
        $mission->sources()->attach($source);
        Livewire::test(AiMissionRoom::class)->call('openMission', $mission->id)->call('buildBrief')->call('approveBrief');

        $prompts = [];
        Http::fake(['*/chat/completions' => function (Request $request) use (&$prompts) {
            $prompts[] = $request->data()['messages'][0]['content'];

            return Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => json_encode(['findings' => [], 'summary' => 'x', 'decisions' => []])]]]]);
        }]);

        app(MissionRunner::class)->runRound($mission->fresh(), $this->admin);

        $this->assertNotEmpty($prompts);

        foreach ($prompts as $prompt) {
            $this->assertStringContainsString('هیچ‌وقت زیر قیمت تمام‌شده نفروش', $prompt);
            $this->assertStringContainsString('خط قرمزها', $prompt);
            $this->assertStringNotContainsString('متن خام خیلی محرمانه', $prompt);
        }
    }

    public function test_editing_the_brief_asks_for_a_new_approval(): void
    {
        $mission = $this->mission();
        $mission->sources()->attach(AiMissionSource::factory()->create(['user_id' => $this->admin->id, 'digest' => $this->digest()])->id);

        Livewire::test(AiMissionRoom::class)
            ->call('openMission', $mission->id)
            ->call('buildBrief')
            ->call('approveBrief')
            ->set('briefLists.red_lines', "خط اول\nخط دوم")
            ->call('saveBrief');

        $mission->refresh();
        $this->assertSame(AiMissionBriefStatus::Review, $mission->brief_status);
        $this->assertSame(['خط اول', 'خط دوم'], $mission->brief['red_lines']);
        $this->assertNotNull(MissionBrief::blocksRun($mission));
    }

    public function test_only_the_owner_can_see_attach_or_delete_a_source(): void
    {
        $other = User::factory()->create();
        $other->assignRole(Role::firstOrCreate(['name' => 'admin']));
        $theirs = AiMissionSource::factory()->create(['user_id' => $other->id, 'title' => 'فایل محرمانهٔ دیگری']);
        $mission = $this->mission();

        Livewire::test(AiMissionRoom::class)
            ->call('openMission', $mission->id)
            ->assertDontSee('فایل محرمانهٔ دیگری')
            ->call('toggleSource', $theirs->id)
            ->call('deleteSource', $theirs->id)
            ->call('setSourceKind', $theirs->id, 'background');

        $this->assertSame(0, $mission->sources()->count());
        $this->assertNotNull($theirs->fresh());
        $this->assertSame(AiMissionSourceKind::Policy, $theirs->fresh()->kind);
    }

    public function test_deleting_a_source_removes_its_file_and_detaches_it(): void
    {
        $mission = $this->mission();
        Storage::disk('local')->put('mission-sources/a.txt', 'x');
        $source = AiMissionSource::factory()->create(['user_id' => $this->admin->id, 'path' => 'mission-sources/a.txt']);
        $mission->sources()->attach($source);

        Livewire::test(AiMissionRoom::class)->call('deleteSource', $source->id);

        Storage::disk('local')->assertMissing('mission-sources/a.txt');
        $this->assertSame(0, AiMissionSource::count());
        $this->assertSame(0, $mission->sources()->count());
    }

    public function test_the_library_shows_on_the_landing_page_and_the_card_inside_a_mission(): void
    {
        $mission = $this->mission();
        AiMissionSource::factory()->create(['user_id' => $this->admin->id, 'title' => 'برنامهٔ راهبردی ۱۴۰۵']);

        Livewire::test(AiMissionRoom::class)
            ->assertSee('کتابخانهٔ منابع')
            ->assertSee('برنامهٔ راهبردی ۱۴۰۵')
            ->call('openMission', $mission->id)
            ->assertSee('منابع و استراتژی')
            ->assertSee('برنامهٔ راهبردی ۱۴۰۵');
    }
}
