<?php

namespace Tests\Feature;

use App\Domain\Ai\Missions\MissionRunner;
use App\Enums\AiMissionCardKind;
use App\Enums\AiMissionConfidence;
use App\Enums\AiMissionRoleType;
use App\Enums\AiMissionStatus;
use App\Filament\Pages\AiMissionRoom;
use App\Models\AiAssistantProfile;
use App\Models\AiMission;
use App\Models\AiMissionCard;
use App\Models\AiMissionRole;
use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The operations room: a mission's team (specialists, critic, coordinator)
 * reports findings with sources onto a board and the coordinator drafts
 * decisions for the manager. The team only reads and reports.
 */
class AiMissionRoomTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::firstOrCreate(['name' => 'admin']));
        $this->actingAs($this->admin);
        AiProvider::factory()->active()->create();
    }

    /**
     * @return array{mission: AiMission, specialist: AiAssistantProfile, critic: AiAssistantProfile, coordinator: AiAssistantProfile}
     */
    private function team(bool $withCritic = true, array $specialistAttributes = []): array
    {
        $specialist = AiAssistantProfile::factory()->create(['created_by' => $this->admin->id, 'name' => 'متخصص رزرو'] + $specialistAttributes);
        $critic = AiAssistantProfile::factory()->create(['created_by' => $this->admin->id, 'name' => 'منتقد تیم']);
        $coordinator = AiAssistantProfile::factory()->create(['created_by' => $this->admin->id, 'name' => 'هماهنگ‌کنندهٔ تیم']);
        $mission = AiMission::factory()->create(['created_by' => $this->admin->id, 'goal' => 'وضعیت هفتهٔ آینده', 'max_rounds' => 2]);

        AiMissionRole::factory()->create(['ai_mission_id' => $mission->id, 'ai_assistant_profile_id' => $specialist->id, 'role' => AiMissionRoleType::Specialist, 'directive' => 'اتاق‌ها را بررسی کن']);
        if ($withCritic) {
            AiMissionRole::factory()->create(['ai_mission_id' => $mission->id, 'ai_assistant_profile_id' => $critic->id, 'role' => AiMissionRoleType::Critic]);
        }
        AiMissionRole::factory()->create(['ai_mission_id' => $mission->id, 'ai_assistant_profile_id' => $coordinator->id, 'role' => AiMissionRoleType::Coordinator]);

        return compact('mission', 'specialist', 'critic', 'coordinator');
    }

    private function reply(string $content): array
    {
        return ['choices' => [['message' => ['role' => 'assistant', 'content' => $content]]]];
    }

    /**
     * Answers each member by the role named in the system prompt.
     *
     * @param  array<string, string|callable>  $answers  role label => content (or a callable returning it)
     */
    private function fakeTeam(array $answers): void
    {
        Http::fake(['*/chat/completions' => function (Request $request) use ($answers) {
            $system = $request->data()['messages'][0]['content'];

            foreach ($answers as $label => $answer) {
                if (str_contains($system, "نقش «{$label}»")) {
                    return Http::response($this->reply(is_callable($answer) ? $answer() : $answer));
                }
            }

            return Http::response(['error' => ['message' => 'unexpected role']], 400);
        }]);
    }

    public function test_a_round_puts_sourced_findings_critiques_and_a_draft_report_on_the_board(): void
    {
        ['mission' => $mission] = $this->team();

        $this->fakeTeam([
            'متخصص' => json_encode(['findings' => [['claim' => 'سه‌شنبه فقط ۲ اتاق خالی است.', 'source' => 'check_room_availability', 'confidence' => 'high']]], JSON_UNESCAPED_UNICODE),
            'منتقد' => fn () => json_encode(['critiques' => [['card_id' => AiMissionCard::sole()->id, 'objection' => 'بازهٔ تاریخ مشخص نیست.', 'severity' => 'medium']]], JSON_UNESCAPED_UNICODE),
            'هماهنگ‌کننده' => json_encode(['summary' => 'سه‌شنبه شلوغ است.', 'decisions' => [['title' => 'قیمت سه‌شنبه را بالا ببر', 'detail' => 'فقط ۲ اتاق خالی']], 'uncertain' => []], JSON_UNESCAPED_UNICODE),
        ]);

        $result = app(MissionRunner::class)->runRound($mission, $this->admin);

        $this->assertTrue($result['ok']);
        $mission->refresh();
        $this->assertSame(AiMissionStatus::WaitingApproval, $mission->status);
        $this->assertSame(1, $mission->rounds_run);

        $finding = AiMissionCard::where('kind', AiMissionCardKind::Finding)->sole();
        $this->assertSame('check_room_availability', $finding->source);
        $this->assertSame(AiMissionConfidence::High, $finding->confidence);
        $this->assertFalse($finding->unsourced);

        $critique = AiMissionCard::where('kind', AiMissionCardKind::Critique)->sole();
        $this->assertSame($finding->id, $critique->target_card_id);

        $this->assertSame('قیمت سه‌شنبه را بالا ببر', $mission->report['decisions'][0]['title']);
        $this->assertContains('مورد نقد: سه‌شنبه فقط ۲ اتاق خالی است. — بازهٔ تاریخ مشخص نیست.', $mission->report['uncertain']);
    }

    public function test_a_finding_without_any_source_is_low_confidence_and_listed_as_uncertain(): void
    {
        ['mission' => $mission] = $this->team(withCritic: false);

        $this->fakeTeam([
            'متخصص' => json_encode(['findings' => [['claim' => 'احتمالاً شلوغ می‌شود.', 'confidence' => 'high']]], JSON_UNESCAPED_UNICODE),
            'هماهنگ‌کننده' => json_encode(['summary' => 'خلاصه', 'decisions' => [], 'uncertain' => []], JSON_UNESCAPED_UNICODE),
        ]);

        app(MissionRunner::class)->runRound($mission, $this->admin);

        $card = AiMissionCard::where('kind', AiMissionCardKind::Finding)->sole();
        $this->assertTrue($card->unsourced);
        $this->assertSame(AiMissionConfidence::Low, $card->confidence);
        $this->assertContains('بدون منبع: احتمالاً شلوغ می‌شود.', $mission->refresh()->report['uncertain']);
        $this->assertSame(AiMissionStatus::Done, $mission->status);
    }

    public function test_a_finding_made_with_a_lookup_gets_that_lookup_as_its_source(): void
    {
        ['mission' => $mission] = $this->team(withCritic: false, specialistAttributes: ['allowed_tools' => ['check_support_ticket_status']]);
        $specialistCalls = 0;

        Http::fake(['*/chat/completions' => function (Request $request) use (&$specialistCalls) {
            $system = $request->data()['messages'][0]['content'];

            if (str_contains($system, 'نقش «متخصص»')) {
                $specialistCalls++;

                return Http::response($specialistCalls === 1
                    ? ['choices' => [['message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => [['id' => 'c1', 'type' => 'function', 'function' => ['name' => 'check_support_ticket_status', 'arguments' => '{}']]]]]]]
                    : $this->reply(json_encode(['findings' => [['claim' => 'تیکت باز نداریم.']]], JSON_UNESCAPED_UNICODE)));
            }

            return Http::response($this->reply(json_encode(['summary' => 'خلاصه', 'decisions' => [], 'uncertain' => []], JSON_UNESCAPED_UNICODE)));
        }]);

        app(MissionRunner::class)->runRound($mission, $this->admin);

        $card = AiMissionCard::where('kind', AiMissionCardKind::Finding)->sole();
        $this->assertSame('ابزار: check_support_ticket_status', $card->source);
        $this->assertFalse($card->unsourced);
    }

    public function test_an_answer_that_is_not_json_is_kept_as_one_low_confidence_finding(): void
    {
        ['mission' => $mission] = $this->team(withCritic: false);

        $this->fakeTeam(['متخصص' => 'یک پاسخ ساده بدون ساختار', 'هماهنگ‌کننده' => 'جمع‌بندی ساده']);

        app(MissionRunner::class)->runRound($mission, $this->admin);

        $card = AiMissionCard::where('kind', AiMissionCardKind::Finding)->sole();
        $this->assertSame('یک پاسخ ساده بدون ساختار', $card->claim);
        $this->assertSame(AiMissionConfidence::Low, $card->confidence);
        $this->assertSame('جمع‌بندی ساده', $mission->refresh()->report['summary']);
    }

    public function test_members_are_never_offered_the_consult_tool(): void
    {
        ['mission' => $mission] = $this->team(withCritic: false, specialistAttributes: ['allowed_tools' => ['check_support_ticket_status']]);
        AiAssistantProfile::factory()->create(['created_by' => $this->admin->id, 'consultable' => true]);

        $this->fakeTeam(['متخصص' => '{"findings":[]}', 'هماهنگ‌کننده' => '{"summary":"x","decisions":[],"uncertain":[]}']);

        app(MissionRunner::class)->runRound($mission, $this->admin);

        Http::assertSent(fn (Request $request) => ! in_array('consult_assistant', collect($request->data()['tools'] ?? [])->pluck('function.name')->all(), true));
    }

    public function test_a_failed_member_call_leaves_no_half_written_round_and_can_be_retried(): void
    {
        ['mission' => $mission] = $this->team(withCritic: false);

        Http::fake(['*/chat/completions' => Http::response(['error' => ['message' => 'quota exceeded']], 429)]);

        $result = app(MissionRunner::class)->runRound($mission, $this->admin);

        $this->assertFalse($result['ok']);
        $mission->refresh();
        $this->assertSame(AiMissionStatus::Failed, $mission->status);
        $this->assertStringContainsString('متخصص رزرو', $mission->last_error);
        $this->assertSame(0, $mission->rounds_run);
        $this->assertSame(0, AiMissionCard::count());
        $this->assertTrue($mission->canRunAnotherRound());
    }

    public function test_the_round_cap_and_ownership_are_enforced(): void
    {
        ['mission' => $mission] = $this->team(withCritic: false);
        $mission->update(['rounds_run' => 2]);

        $this->assertFalse(app(MissionRunner::class)->runRound($mission, $this->admin)['ok']);

        $mission->update(['rounds_run' => 0]);
        $stranger = User::factory()->create();
        Http::fake();

        $this->assertFalse(app(MissionRunner::class)->runRound($mission, $stranger)['ok']);
        Http::assertNothingSent();
    }

    public function test_the_page_creates_a_mission_and_validates_the_team(): void
    {
        $coordinator = AiAssistantProfile::factory()->create(['created_by' => $this->admin->id]);
        $specialist = AiAssistantProfile::factory()->create(['created_by' => $this->admin->id]);

        Livewire::test(AiMissionRoom::class)
            ->call('startCreate')
            ->set('missionTitle', 'مأموریت آزمایشی')
            ->set('missionGoal', 'یک هدف')
            ->set('roleRows', [
                ['profile_id' => $specialist->id, 'role' => 'specialist', 'directive' => ''],
                ['profile_id' => $coordinator->id, 'role' => 'specialist', 'directive' => ''],
            ])
            ->call('createMission');

        $this->assertSame(0, AiMission::count());

        Livewire::test(AiMissionRoom::class)
            ->call('startCreate')
            ->set('missionTitle', 'مأموریت آزمایشی')
            ->set('missionGoal', 'یک هدف')
            ->set('roleRows', [
                ['profile_id' => $coordinator->id, 'role' => 'coordinator', 'directive' => ''],
                ['profile_id' => $specialist->id, 'role' => 'specialist', 'directive' => 'اتاق‌ها'],
            ])
            ->call('createMission')
            ->assertSet('showCreate', false);

        $mission = AiMission::sole();
        $this->assertSame($this->admin->id, $mission->created_by);
        $this->assertCount(2, $mission->roles);
    }

    public function test_a_team_member_that_is_someone_elses_private_assistant_is_refused(): void
    {
        $mine = AiAssistantProfile::factory()->create(['created_by' => $this->admin->id]);
        $private = AiAssistantProfile::factory()->create(['created_by' => User::factory()->create()->id, 'is_shared' => false]);

        Livewire::test(AiMissionRoom::class)
            ->call('startCreate')
            ->set('missionTitle', 'مأموریت')
            ->set('missionGoal', 'هدف')
            ->set('roleRows', [
                ['profile_id' => $mine->id, 'role' => 'coordinator', 'directive' => ''],
                ['profile_id' => $private->id, 'role' => 'specialist', 'directive' => ''],
            ])
            ->call('createMission');

        $this->assertSame(0, AiMission::count());
    }

    public function test_the_page_runs_a_round_and_shows_the_board_and_the_draft_report(): void
    {
        ['mission' => $mission] = $this->team(withCritic: false);

        $this->fakeTeam([
            'متخصص' => json_encode(['findings' => [['claim' => 'پنجشنبه پر است.', 'source' => 'check_room_availability', 'confidence' => 'high']]], JSON_UNESCAPED_UNICODE),
            'هماهنگ‌کننده' => json_encode(['summary' => 'پنجشنبه پر است.', 'decisions' => [['title' => 'نرخ پنجشنبه را بررسی کن', 'detail' => 'اشغال کامل']], 'uncertain' => []], JSON_UNESCAPED_UNICODE),
        ]);

        Livewire::test(AiMissionRoom::class)
            ->call('openMission', $mission->id)
            ->call('runRound')
            ->assertSee('پنجشنبه پر است.')
            ->assertSee('پیش‌نویس تصمیم')
            ->assertSee('نرخ پنجشنبه را بررسی کن')
            ->assertSee('هیچ اقدامی انجام نشده');
    }

    public function test_a_user_cannot_open_someone_elses_mission(): void
    {
        $other = AiMission::factory()->create(['title' => 'مأموریت محرمانهٔ دیگری']);

        Livewire::test(AiMissionRoom::class)
            ->call('openMission', $other->id)
            ->assertSet('missionId', null)
            ->assertDontSee('مأموریت محرمانهٔ دیگری');
    }

    public function test_a_user_without_the_permission_is_denied(): void
    {
        $this->artisan('access-control:sync');
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'بدون دسترسی اتاق عملیات']));

        $this->actingAs($user)->get(route('filament.admin.pages.ai-mission-room'))->assertForbidden();
    }
}
