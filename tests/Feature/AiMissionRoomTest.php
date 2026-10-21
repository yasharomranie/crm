<?php

namespace Tests\Feature;

use App\Domain\Ai\Missions\MissionRunner;
use App\Enums\AiMissionCardKind;
use App\Enums\AiMissionCardStatus;
use App\Enums\AiMissionConfidence;
use App\Enums\AiMissionRoleType;
use App\Enums\AiMissionStatus;
use App\Filament\Pages\AiMissionRoom;
use App\Jobs\RunMissionStepJob;
use App\Models\AiAssistantProfile;
use App\Models\AiMission;
use App\Models\AiMissionCard;
use App\Models\AiMissionRole;
use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
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
            ->set('specialists', [['profile_id' => $specialist->id, 'directive' => '']])
            ->call('createMission');

        $this->assertSame(0, AiMission::count(), 'a coordinator is required');

        Livewire::test(AiMissionRoom::class)
            ->call('startCreate')
            ->set('missionTitle', 'مأموریت آزمایشی')
            ->set('missionGoal', 'یک هدف')
            ->set('coordinatorId', $coordinator->id)
            ->set('specialists', [['profile_id' => $specialist->id, 'directive' => 'اتاق‌ها']])
            ->call('createMission')
            ->assertSet('showCreate', false);

        $mission = AiMission::sole();
        $this->assertSame($this->admin->id, $mission->created_by);
        $this->assertCount(2, $mission->roles);
    }

    public function test_an_empty_slot_or_a_repeated_assistant_blocks_creation(): void
    {
        $coordinator = AiAssistantProfile::factory()->create(['created_by' => $this->admin->id]);

        $component = Livewire::test(AiMissionRoom::class)
            ->call('startCreate')
            ->set('missionTitle', 'مأموریت')
            ->set('missionGoal', 'هدف')
            ->set('coordinatorId', $coordinator->id)
            ->set('specialists', [['profile_id' => null, 'directive' => '']])
            ->call('createMission');

        $this->assertSame(0, AiMission::count(), 'a slot without an assistant');

        $component->set('specialists', [['profile_id' => $coordinator->id, 'directive' => '']])->call('createMission');

        $this->assertSame(0, AiMission::count(), 'the same assistant in two roles');
    }

    public function test_the_picker_assigns_an_assistant_to_the_slot_it_was_opened_for(): void
    {
        $coordinator = AiAssistantProfile::factory()->create(['created_by' => $this->admin->id, 'name' => 'دستیار انتخابی']);
        $specialist = AiAssistantProfile::factory()->create(['created_by' => $this->admin->id]);
        $private = AiAssistantProfile::factory()->create(['created_by' => User::factory()->create()->id, 'is_shared' => false]);

        Livewire::test(AiMissionRoom::class)
            ->call('startCreate')
            ->call('openPicker', 'coordinator')
            ->assertSee('دستیار انتخابی')
            ->call('pickProfile', $coordinator->id)
            ->assertSet('coordinatorId', $coordinator->id)
            ->assertSet('pickingFor', null)
            ->call('openPicker', 'specialist:0')
            ->call('pickProfile', $private->id)
            ->assertSet('specialists.0.profile_id', null)
            ->call('pickProfile', $specialist->id)
            ->assertSet('specialists.0.profile_id', $specialist->id);
    }

    public function test_specialists_can_be_added_up_to_four_and_the_critic_is_optional(): void
    {
        $critic = AiAssistantProfile::factory()->create(['created_by' => $this->admin->id]);

        $component = Livewire::test(AiMissionRoom::class)->call('startCreate');

        foreach (range(1, 5) as $unused) {
            $component->call('addSpecialist');
        }

        $this->assertCount(4, $component->get('specialists'));

        $component->call('toggleCritic')->assertSet('withCritic', true)
            ->set('criticId', $critic->id)
            ->call('toggleCritic')->assertSet('withCritic', false)->assertSet('criticId', null);
    }

    public function test_a_template_fills_the_goal_directives_and_needed_tools_but_keeps_chosen_assistants(): void
    {
        $specialist = AiAssistantProfile::factory()->create(['created_by' => $this->admin->id]);

        Livewire::test(AiMissionRoom::class)
            ->call('startCreate')
            ->set('specialists', [['profile_id' => $specialist->id, 'directive' => '']])
            ->call('applyTemplate', 'week-ahead')
            ->assertSet('templateKey', 'week-ahead')
            ->assertSet('missionTitle', 'وضعیت رزرو و پشتیبانی هفتهٔ آینده')
            ->assertSet('withCritic', true)
            ->assertSet('specialists.0.profile_id', $specialist->id)
            ->assertSet('specialists.0.needs', ['get_occupancy_forecast', 'check_room_availability'])
            ->assertSet('specialists.1.profile_id', null)
            ->assertSee('ابزار لازم برای این جایگاه');
    }

    public function test_a_template_without_a_critic_drops_a_chosen_one_and_an_unknown_key_changes_nothing(): void
    {
        $critic = AiAssistantProfile::factory()->create(['created_by' => $this->admin->id]);

        Livewire::test(AiMissionRoom::class)
            ->call('startCreate')
            ->call('toggleCritic')->set('criticId', $critic->id)
            ->call('applyTemplate', 'no-such-template')
            ->assertSet('templateKey', null)
            ->assertSet('withCritic', true)
            ->call('applyTemplate', 'morning-brief')
            ->assertSet('withCritic', false)
            ->assertSet('criticId', null)
            ->assertSet('missionTitle', 'گزارش صبحگاهی مدیریت');
    }

    public function test_a_template_mission_is_created_with_its_suggested_schedule_only_when_asked(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-21 10:00', 'Asia/Tehran'));
        $coordinator = AiAssistantProfile::factory()->create(['created_by' => $this->admin->id]);
        $first = AiAssistantProfile::factory()->create(['created_by' => $this->admin->id]);
        $second = AiAssistantProfile::factory()->create(['created_by' => $this->admin->id]);

        $component = Livewire::test(AiMissionRoom::class)
            ->call('startCreate')
            ->call('applyTemplate', 'morning-brief')
            ->set('coordinatorId', $coordinator->id)
            ->set('specialists.0.profile_id', $first->id)
            ->set('specialists.1.profile_id', $second->id)
            ->call('createMission');

        $plain = AiMission::sole();
        $this->assertNull($plain->schedule);

        $component->call('startCreate')
            ->call('applyTemplate', 'morning-brief')
            ->set('applyTemplateSchedule', true)
            ->set('coordinatorId', $coordinator->id)
            ->set('specialists.0.profile_id', $first->id)
            ->set('specialists.1.profile_id', $second->id)
            ->call('createMission');

        $scheduled = AiMission::whereKeyNot($plain->id)->sole();
        $this->assertSame('daily', $scheduled->schedule);
        $this->assertSame('07:30', $scheduled->schedule_time);
        $this->assertSame('2026-10-22 07:30', $scheduled->next_run_at->timezone('Asia/Tehran')->format('Y-m-d H:i'));
        Carbon::setTestNow();
    }

    public function test_the_picker_favours_assistants_that_have_the_tools_a_slot_needs(): void
    {
        AiAssistantProfile::factory()->create(['created_by' => $this->admin->id, 'name' => 'دستیار مجهز', 'allowed_tools' => ['get_hotel_report']]);
        AiAssistantProfile::factory()->create(['created_by' => $this->admin->id, 'name' => 'دستیار بی‌ابزار']);

        Livewire::test(AiMissionRoom::class)
            ->call('startCreate')
            ->call('applyTemplate', 'weekly-performance')
            ->call('openPicker', 'specialist:0')
            ->assertSeeInOrder(['دستیار مجهز', '✓ مناسب این جایگاه', 'دستیار بی‌ابزار', 'ابزار لازم را ندارد']);
    }

    public function test_a_team_member_that_is_someone_elses_private_assistant_is_refused(): void
    {
        $mine = AiAssistantProfile::factory()->create(['created_by' => $this->admin->id]);
        $private = AiAssistantProfile::factory()->create(['created_by' => User::factory()->create()->id, 'is_shared' => false]);

        Livewire::test(AiMissionRoom::class)
            ->call('startCreate')
            ->set('missionTitle', 'مأموریت')
            ->set('missionGoal', 'هدف')
            ->set('coordinatorId', $mine->id)
            ->set('specialists', [['profile_id' => $private->id, 'directive' => '']])
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
            ->assertSee('هیچ اقدامی انجام نشده')
            ->assertSee('متخصص رزرو');
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

    private function replyWithUsage(string $content, int $tokens): array
    {
        return $this->reply($content) + ['usage' => ['total_tokens' => $tokens]];
    }

    public function test_the_page_starts_a_round_in_the_background_and_shows_the_progress(): void
    {
        Queue::fake();
        ['mission' => $mission] = $this->team();

        Livewire::test(AiMissionRoom::class)->call('openMission', $mission->id)->call('runRound');

        $mission->refresh();
        $this->assertSame(AiMissionStatus::Running, $mission->status);
        $this->assertSame(3, $mission->step_total);
        Queue::assertPushed(RunMissionStepJob::class, fn (RunMissionStepJob $job) => $job->missionId === $mission->id && $job->round === 1 && $job->step === 0);

        Livewire::test(AiMissionRoom::class)->call('openMission', $mission->id)->assertSee('در صف اجرا')->assertSee('توقف');
    }

    public function test_the_step_jobs_chain_one_member_at_a_time_and_finish_with_the_report(): void
    {
        Queue::fake();
        ['mission' => $mission] = $this->team();
        $this->fakeTeam([
            'متخصص' => json_encode(['findings' => [['claim' => 'یافتهٔ مرحله‌ای', 'source' => 'check_room_availability']]], JSON_UNESCAPED_UNICODE),
            'منتقد' => '{"critiques":[]}',
            'هماهنگ‌کننده' => json_encode(['summary' => 'پایان', 'decisions' => [['title' => 'یک تصمیم', 'detail' => '']], 'uncertain' => []], JSON_UNESCAPED_UNICODE),
        ]);
        $runner = app(MissionRunner::class);

        $this->assertTrue($runner->start($mission, $this->admin)['ok']);

        (new RunMissionStepJob($mission->id, $this->admin->id, 1, 0))->handle($runner);
        Queue::assertPushed(RunMissionStepJob::class, fn (RunMissionStepJob $job) => $job->step === 1);

        (new RunMissionStepJob($mission->id, $this->admin->id, 1, 1))->handle($runner);
        Queue::assertPushed(RunMissionStepJob::class, fn (RunMissionStepJob $job) => $job->step === 2);
        $this->assertSame(AiMissionStatus::Running, $mission->refresh()->status);

        (new RunMissionStepJob($mission->id, $this->admin->id, 1, 2))->handle($runner);

        $mission->refresh();
        $this->assertSame(AiMissionStatus::WaitingApproval, $mission->status);
        $this->assertNull($mission->current_step);
        $this->assertSame(1, $mission->rounds_run);
        Queue::assertPushedTimes(RunMissionStepJob::class, 2);
    }

    public function test_a_stop_request_ends_the_run_at_the_next_step_and_discards_the_half_round(): void
    {
        Queue::fake();
        ['mission' => $mission] = $this->team(withCritic: false);
        $this->fakeTeam(['متخصص' => json_encode(['findings' => [['claim' => 'x', 'source' => 's']]]), 'هماهنگ‌کننده' => '{}']);
        $runner = app(MissionRunner::class);
        $runner->start($mission, $this->admin);

        (new RunMissionStepJob($mission->id, $this->admin->id, 1, 0))->handle($runner);
        $this->assertSame(1, AiMissionCard::count());

        Livewire::test(AiMissionRoom::class)->call('openMission', $mission->id)->call('stopMission');
        $this->assertTrue($mission->refresh()->cancel_requested);

        (new RunMissionStepJob($mission->id, $this->admin->id, 1, 1))->handle($runner);

        $mission->refresh();
        $this->assertSame(AiMissionStatus::Stopped, $mission->status);
        $this->assertSame(0, AiMissionCard::count());
        $this->assertSame(0, $mission->rounds_run);
        $this->assertTrue($mission->canRunAnotherRound());
    }

    public function test_a_run_whose_worker_died_is_released_by_the_stop_button_at_once(): void
    {
        ['mission' => $mission] = $this->team(withCritic: false);
        $mission->update(['status' => AiMissionStatus::Running]);
        AiMission::whereKey($mission->id)->update(['updated_at' => now()->subMinutes(10)]);

        Livewire::test(AiMissionRoom::class)->call('openMission', $mission->id)->call('stopMission');

        $this->assertSame(AiMissionStatus::Stopped, $mission->refresh()->status);
    }

    public function test_token_usage_is_counted_and_the_budget_stops_the_next_step(): void
    {
        Queue::fake();
        ['mission' => $mission] = $this->team(withCritic: false);
        $mission->update(['token_budget' => 100]);
        Http::fake(['*/chat/completions' => Http::response($this->replyWithUsage(json_encode(['findings' => [['claim' => 'x', 'source' => 's']]]), 150))]);
        $runner = app(MissionRunner::class);
        $runner->start($mission, $this->admin);

        (new RunMissionStepJob($mission->id, $this->admin->id, 1, 0))->handle($runner);
        $this->assertSame(150, $mission->refresh()->tokens_used);

        (new RunMissionStepJob($mission->id, $this->admin->id, 1, 1))->handle($runner);

        $mission->refresh();
        $this->assertSame(AiMissionStatus::Failed, $mission->status);
        $this->assertStringContainsString('سقف توکن', $mission->last_error);
        $this->assertSame(0, AiMissionCard::count());
    }

    public function test_a_mission_over_its_budget_cannot_start_until_the_budget_is_raised(): void
    {
        ['mission' => $mission] = $this->team(withCritic: false);
        $mission->update(['token_budget' => 1000, 'tokens_used' => 1500]);

        $this->assertFalse(app(MissionRunner::class)->start($mission, $this->admin)['ok']);

        Livewire::test(AiMissionRoom::class)->call('openMission', $mission->id)->call('saveBudget', 5000);

        $this->assertSame(5000, $mission->refresh()->token_budget);
        $this->assertTrue(app(MissionRunner::class)->start($mission, $this->admin)['ok']);
    }

    public function test_a_failed_job_abandons_the_round_that_was_running(): void
    {
        ['mission' => $mission] = $this->team(withCritic: false);
        app(MissionRunner::class)->start($mission, $this->admin);

        (new RunMissionStepJob($mission->id, $this->admin->id, 1, 0))->failed(new \RuntimeException('boom'));

        $this->assertSame(AiMissionStatus::Failed, $mission->refresh()->status);
    }

    /**
     * Fakes the team and records, by role label, the user message each member received.
     *
     * @param  array<string, string>  $seen
     */
    private function recordMessages(array $answers, array &$seen): void
    {
        Http::fake(['*/chat/completions' => function (Request $request) use ($answers, &$seen) {
            $messages = $request->data()['messages'];
            $system = $messages[0]['content'];

            foreach ($answers as $label => $answer) {
                if (str_contains($system, "نقش «{$label}»")) {
                    $seen[$label] = $messages[1]['content'];

                    return Http::response($this->reply($answer));
                }
            }

            return Http::response(['error' => ['message' => 'unexpected role']], 400);
        }]);
    }

    public function test_rejected_findings_are_left_out_of_what_the_team_reads_and_locked_ones_are_marked(): void
    {
        ['mission' => $mission, 'specialist' => $specialist] = $this->team(withCritic: false);
        $mission->update(['rounds_run' => 1]);
        foreach ([['یافتهٔ ردشده', AiMissionCardStatus::Rejected], ['یافتهٔ تأییدشده', AiMissionCardStatus::Locked], ['یافتهٔ باز', AiMissionCardStatus::Open]] as [$claim, $status]) {
            AiMissionCard::factory()->create(['ai_mission_id' => $mission->id, 'ai_assistant_profile_id' => $specialist->id, 'round' => 1, 'claim' => $claim, 'status' => $status]);
        }

        $seen = [];
        $this->recordMessages(['متخصص' => '{"findings":[]}', 'هماهنگ‌کننده' => '{"summary":"x","decisions":[],"uncertain":[]}'], $seen);

        app(MissionRunner::class)->runRound($mission, $this->admin);

        foreach (['متخصص', 'هماهنگ‌کننده'] as $label) {
            $this->assertStringNotContainsString('یافتهٔ ردشده', $seen[$label], $label);
            $this->assertStringContainsString('یافتهٔ باز', $seen[$label], $label);
        }
        $this->assertStringContainsString('یافتهٔ تأییدشده', $seen['هماهنگ‌کننده']);
        $this->assertStringContainsString('[تأییدشدهٔ مدیر]', $seen['هماهنگ‌کننده']);
    }

    public function test_only_open_unsourced_findings_are_listed_as_uncertain(): void
    {
        ['mission' => $mission, 'specialist' => $specialist] = $this->team(withCritic: false);
        foreach ([['ردشدهٔ بی‌منبع', AiMissionCardStatus::Rejected], ['تأییدشدهٔ بی‌منبع', AiMissionCardStatus::Locked], ['بازِ بی‌منبع', AiMissionCardStatus::Open]] as [$claim, $status]) {
            AiMissionCard::factory()->create(['ai_mission_id' => $mission->id, 'ai_assistant_profile_id' => $specialist->id, 'round' => 1, 'claim' => $claim, 'status' => $status, 'unsourced' => true, 'source' => null]);
        }

        $this->fakeTeam(['متخصص' => '{"findings":[]}', 'هماهنگ‌کننده' => '{"summary":"x","decisions":[],"uncertain":[]}']);

        app(MissionRunner::class)->runRound($mission, $this->admin);

        $this->assertSame(['بدون منبع: بازِ بی‌منبع'], $mission->refresh()->report['uncertain']);
    }

    public function test_the_managers_notes_reach_every_member_of_the_next_steps(): void
    {
        ['mission' => $mission] = $this->team();
        $mission->update(['notes' => [['text' => 'روی ویلاهای شمال تمرکز کن', 'at' => now()->toIso8601String()]]]);
        AiMissionCard::factory()->create(['ai_mission_id' => $mission->id, 'round' => 1]);

        $seen = [];
        $this->recordMessages(['متخصص' => '{"findings":[]}', 'منتقد' => '{"critiques":[]}', 'هماهنگ‌کننده' => '{"summary":"x","decisions":[],"uncertain":[]}'], $seen);

        app(MissionRunner::class)->runRound($mission, $this->admin);

        foreach (['متخصص', 'منتقد', 'هماهنگ‌کننده'] as $label) {
            $this->assertStringContainsString('روی ویلاهای شمال تمرکز کن', $seen[$label], $label);
        }
    }

    public function test_the_page_adds_and_removes_notes_with_limits(): void
    {
        ['mission' => $mission] = $this->team(withCritic: false);

        $component = Livewire::test(AiMissionRoom::class)->call('openMission', $mission->id)
            ->set('noteText', '   ')->call('addNote');
        $this->assertNull($mission->refresh()->notes);

        $component->set('noteText', 'پیام اول')->call('addNote')->assertSet('noteText', '');
        $component->set('noteText', 'پیام دوم')->call('addNote');
        $this->assertSame(['پیام اول', 'پیام دوم'], collect($mission->refresh()->notes)->pluck('text')->all());

        $component->call('removeNote', 0);
        $this->assertSame(['پیام دوم'], collect($mission->refresh()->notes)->pluck('text')->all());

        $mission->update(['notes' => array_fill(0, 10, ['text' => 'x', 'at' => now()->toIso8601String()])]);
        $component->set('noteText', 'یازدهم')->call('addNote');
        $this->assertCount(10, $mission->refresh()->notes);
    }

    public function test_the_manager_can_lock_reject_and_reopen_a_finding_but_not_while_it_runs(): void
    {
        ['mission' => $mission, 'specialist' => $specialist] = $this->team(withCritic: false);
        $card = AiMissionCard::factory()->create(['ai_mission_id' => $mission->id, 'ai_assistant_profile_id' => $specialist->id]);
        $component = Livewire::test(AiMissionRoom::class)->call('openMission', $mission->id);

        $component->call('setCardStatus', $card->id, 'locked');
        $this->assertSame(AiMissionCardStatus::Locked, $card->refresh()->status);

        $component->call('setCardStatus', $card->id, 'rejected')->assertSee('رد شده توسط شما');
        $this->assertSame(AiMissionCardStatus::Rejected, $card->refresh()->status);

        $component->call('setCardStatus', $card->id, 'open');
        $this->assertSame(AiMissionCardStatus::Open, $card->refresh()->status);

        $component->call('setCardStatus', $card->id, 'bogus');
        $this->assertSame(AiMissionCardStatus::Open, $card->refresh()->status);

        $mission->update(['status' => AiMissionStatus::Running]);
        $component->call('setCardStatus', $card->id, 'rejected');
        $this->assertSame(AiMissionCardStatus::Open, $card->refresh()->status);
    }

    public function test_a_critique_never_targets_a_rejected_finding(): void
    {
        ['mission' => $mission, 'specialist' => $specialist] = $this->team();
        $rejected = AiMissionCard::factory()->create(['ai_mission_id' => $mission->id, 'ai_assistant_profile_id' => $specialist->id, 'round' => 1, 'status' => AiMissionCardStatus::Rejected]);

        $this->fakeTeam([
            'متخصص' => '{"findings":[]}',
            'منتقد' => json_encode(['critiques' => [['card_id' => $rejected->id, 'objection' => 'نقد به ردشده', 'severity' => 'low']]]),
            'هماهنگ‌کننده' => '{"summary":"x","decisions":[],"uncertain":[]}',
        ]);

        app(MissionRunner::class)->runRound($mission, $this->admin);

        $this->assertSame(0, AiMissionCard::where('kind', AiMissionCardKind::Critique)->count());
    }
}
