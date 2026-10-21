<?php

namespace Tests\Feature;

use App\Domain\Ai\Missions\MissionRunner;
use App\Domain\Ai\Missions\MissionSchedule;
use App\Enums\AiMissionRoleType;
use App\Enums\AiMissionStatus;
use App\Filament\Pages\AiMissionRoom;
use App\Jobs\RunMissionStepJob;
use App\Models\AiAssistantProfile;
use App\Models\AiMission;
use App\Models\AiMissionCard;
use App\Models\AiMissionRole;
use App\Models\AiMissionRun;
use App\Models\AiProvider;
use App\Models\User;
use App\Notifications\MissionAttentionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Recurring missions: the schedule maths, the command that starts due cycles,
 * the run history, and the alerts that tell the owner a mission needs a look.
 */
class AiMissionRecurrenceTest extends TestCase
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function mission(array $attributes = [], bool $withTeam = true): AiMission
    {
        $mission = AiMission::factory()->create(['created_by' => $this->admin->id] + $attributes);

        if ($withTeam) {
            foreach ([AiMissionRoleType::Specialist, AiMissionRoleType::Coordinator] as $type) {
                AiMissionRole::factory()->create([
                    'ai_mission_id' => $mission->id,
                    'role' => $type,
                    'ai_assistant_profile_id' => AiAssistantProfile::factory()->create(['created_by' => $this->admin->id])->id,
                ]);
            }
        }

        return $mission;
    }

    private function fakeTeam(int $tokens = 0): void
    {
        Http::fake(['*/chat/completions' => function (Request $request) use ($tokens) {
            $system = $request->data()['messages'][0]['content'];
            $content = str_contains($system, 'نقش «هماهنگ‌کننده»')
                ? '{"summary":"خلاصه","decisions":[{"title":"یک تصمیم","detail":"x"}],"uncertain":[]}'
                : '{"findings":[{"claim":"یافته","source":"check_room_availability"}]}';

            return Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => $content]]]] + ($tokens ? ['usage' => ['total_tokens' => $tokens]] : []));
        }]);
    }

    private function assertNext(string $expected, string $frequency, string $time, ?int $weekday = null, ?int $day = null): void
    {
        $next = app(MissionSchedule::class)->nextRun($frequency, $time, $weekday, $day);

        $this->assertSame($expected, $next->timezone('Asia/Tehran')->format('Y-m-d H:i'));
    }

    public function test_the_next_run_follows_the_chosen_frequency_in_tehran_time(): void
    {
        // Wednesday 21 October 2026, 10:00 in Tehran.
        Carbon::setTestNow(Carbon::parse('2026-10-21 10:00', 'Asia/Tehran'));

        $this->assertNext('2026-10-22 08:00', 'daily', '08:00');
        $this->assertNext('2026-10-21 12:00', 'daily', '12:00');
        $this->assertNext('2026-10-24 08:00', 'weekly', '08:00', weekday: 6);
        $this->assertNext('2026-10-28 08:00', 'weekly', '08:00', weekday: 3);
        $this->assertNext('2026-10-21 18:00', 'weekly', '18:00', weekday: 3);
        $this->assertNext('2026-11-15 08:00', 'monthly', '08:00', day: 15);
        $this->assertNext('2026-10-25 08:00', 'monthly', '08:00', day: 25);
        $this->assertNext('2026-11-21 08:00', 'monthly', '08:00', day: 21);
    }

    public function test_the_command_starts_a_clean_cycle_for_due_missions_only(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-10-21 10:00', 'Asia/Tehran'));

        $due = $this->mission(['schedule' => 'daily', 'schedule_time' => '08:00', 'next_run_at' => now()->subHour(), 'rounds_run' => 1, 'tokens_used' => 500, 'report' => ['summary' => 'قدیمی'], 'status' => AiMissionStatus::WaitingApproval]);
        AiMissionCard::factory()->create(['ai_mission_id' => $due->id]);
        $later = $this->mission(['schedule' => 'daily', 'next_run_at' => now()->addHour(), 'rounds_run' => 1]);
        $manual = $this->mission(['rounds_run' => 1]);

        $this->artisan('ai-missions:run-due')->assertSuccessful();

        $due->refresh();
        $this->assertSame(AiMissionStatus::Running, $due->status);
        $this->assertSame(0, $due->rounds_run);
        $this->assertSame(0, $due->tokens_used);
        $this->assertNull($due->report);
        $this->assertSame(0, $due->cards()->count());
        $this->assertSame('scheduled', $due->run_trigger);
        $this->assertSame('2026-10-22 08:00', $due->next_run_at->timezone('Asia/Tehran')->format('Y-m-d H:i'));
        $this->assertNotNull($due->last_scheduled_at);
        Queue::assertPushed(RunMissionStepJob::class, fn (RunMissionStepJob $job) => $job->missionId === $due->id && $job->round === 1 && $job->step === 0);
        Queue::assertPushedTimes(RunMissionStepJob::class, 1);

        $this->assertSame(1, $later->refresh()->rounds_run);
        $this->assertSame(1, $manual->refresh()->rounds_run);
    }

    public function test_a_mission_still_running_is_left_alone_until_the_next_pass(): void
    {
        Queue::fake();
        $running = $this->mission(['schedule' => 'daily', 'next_run_at' => now()->subHour(), 'status' => AiMissionStatus::Running, 'rounds_run' => 0]);
        $before = $running->next_run_at->toIso8601String();

        $this->artisan('ai-missions:run-due')->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertSame($before, $running->refresh()->next_run_at->toIso8601String());
    }

    public function test_a_cycle_that_cannot_start_tells_the_owner_and_waits_for_the_next_one(): void
    {
        Queue::fake();
        Notification::fake();
        $mission = $this->mission(['schedule' => 'daily', 'next_run_at' => now()->subHour()], withTeam: false);

        $this->artisan('ai-missions:run-due')->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertTrue($mission->refresh()->next_run_at->isFuture());
        Notification::assertSentTo($this->admin, MissionAttentionNotification::class, fn ($notification) => $notification->toDatabase($this->admin)['type'] === 'ai_mission_failed');
    }

    public function test_a_finished_round_is_kept_in_the_history_and_the_owner_is_told(): void
    {
        Notification::fake();
        $mission = $this->mission();
        $this->fakeTeam();

        app(MissionRunner::class)->runRound($mission, $this->admin);

        $run = AiMissionRun::sole();
        $this->assertSame($mission->id, $run->ai_mission_id);
        $this->assertSame('manual', $run->trigger);
        $this->assertSame('خلاصه', $run->summary);
        $this->assertSame(AiMissionStatus::WaitingApproval, $run->status);
        Notification::assertSentTo($this->admin, MissionAttentionNotification::class, fn ($notification) => $notification->toDatabase($this->admin)['type'] === 'ai_mission_ready');
    }

    public function test_a_failed_round_alerts_the_owner_but_a_stop_does_not(): void
    {
        Notification::fake();
        $failing = $this->mission();
        Http::fake(['*/chat/completions' => Http::response(['error' => ['message' => 'quota exceeded']], 429)]);

        app(MissionRunner::class)->runRound($failing, $this->admin);

        Notification::assertSentToTimes($this->admin, MissionAttentionNotification::class, 1);

        $stopped = $this->mission();
        app(MissionRunner::class)->start($stopped, $this->admin);
        $stopped->update(['cancel_requested' => true]);
        app(MissionRunner::class)->runStep($stopped, 0, 1, $this->admin);

        $this->assertSame(AiMissionStatus::Stopped, $stopped->refresh()->status);
        Notification::assertSentToTimes($this->admin, MissionAttentionNotification::class, 1);
    }

    public function test_the_budget_warning_is_sent_once_per_run(): void
    {
        Notification::fake();
        $mission = $this->mission(['token_budget' => 1000]);
        AiMissionRole::factory()->create([
            'ai_mission_id' => $mission->id,
            'role' => AiMissionRoleType::Specialist,
            'ai_assistant_profile_id' => AiAssistantProfile::factory()->create(['created_by' => $this->admin->id])->id,
        ]);
        $this->fakeTeam(tokens: 450);

        app(MissionRunner::class)->runRound($mission, $this->admin);

        Notification::assertSentTo($this->admin, MissionAttentionNotification::class, fn ($notification) => $notification->toDatabase($this->admin)['type'] === 'ai_mission_budget_warning');
        $warnings = Notification::sent($this->admin, MissionAttentionNotification::class)->filter(fn ($notification) => $notification->toDatabase($this->admin)['type'] === 'ai_mission_budget_warning');
        $this->assertCount(1, $warnings);
        $this->assertTrue($mission->refresh()->budget_alerted);
    }

    public function test_the_page_saves_and_clears_the_schedule_and_validates_the_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-21 10:00', 'Asia/Tehran'));
        $mission = $this->mission();

        $component = Livewire::test(AiMissionRoom::class)->call('openMission', $mission->id)
            ->set('scheduleFrequency', 'weekly')->set('scheduleTime', '07:30')->set('scheduleWeekday', 6)
            ->call('saveSchedule');

        $mission->refresh();
        $this->assertSame('weekly', $mission->schedule);
        $this->assertSame(6, $mission->schedule_weekday);
        $this->assertNull($mission->schedule_day);
        $this->assertSame('2026-10-24 07:30', $mission->next_run_at->timezone('Asia/Tehran')->format('Y-m-d H:i'));

        $component->set('scheduleTime', '25:99')->call('saveSchedule')->assertHasErrors('scheduleTime');
        $this->assertSame('07:30', $mission->refresh()->schedule_time);

        $component->set('scheduleTime', '07:30')->set('scheduleFrequency', '')->call('saveSchedule');
        $this->assertNull($mission->refresh()->schedule);
        $this->assertNull($mission->next_run_at);
    }

    public function test_opening_a_mission_by_link_loads_its_schedule_and_shows_the_history(): void
    {
        $mission = $this->mission(['schedule' => 'daily', 'schedule_time' => '09:15', 'next_run_at' => now()->addDay()]);
        AiMissionRun::factory()->create(['ai_mission_id' => $mission->id, 'summary' => 'نتیجهٔ دور قبل', 'trigger' => 'scheduled', 'report' => ['summary' => 'نتیجهٔ دور قبل', 'decisions' => [['title' => 'تصمیم قبلی', 'detail' => 'x']], 'uncertain' => []]]);

        Livewire::withQueryParams(['mission' => $mission->id])->test(AiMissionRoom::class)
            ->assertSet('scheduleFrequency', 'daily')
            ->assertSet('scheduleTime', '09:15')
            ->assertSee('اجراهای قبلی')
            ->assertSee('نتیجهٔ دور قبل')
            ->assertSee('تصمیم قبلی')
            ->assertSee('زمان‌بندی‌شده');
    }
}
