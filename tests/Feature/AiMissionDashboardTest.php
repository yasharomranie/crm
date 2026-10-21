<?php

namespace Tests\Feature;

use App\Domain\Ai\Missions\MissionDashboard;
use App\Domain\Ai\Missions\MissionSchedule;
use App\Enums\AiMissionStatus;
use App\Filament\Pages\AiMissionRoom;
use App\Models\AiMission;
use App\Models\AiMissionRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The operations room's landing view: what needs a decision, what runs now,
 * what repeats and the latest results - for the user's own missions only.
 */
class AiMissionDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::firstOrCreate(['name' => 'admin']));
        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function mission(array $attributes = []): AiMission
    {
        return AiMission::factory()->create(['created_by' => $this->admin->id] + $attributes);
    }

    public function test_the_tiles_count_the_users_missions_by_state(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-21 10:00', 'Asia/Tehran'));
        $this->mission(['status' => AiMissionStatus::WaitingApproval]);
        $this->mission(['status' => AiMissionStatus::Failed]);
        $this->mission(['status' => AiMissionStatus::Stopped]);
        $this->mission(['status' => AiMissionStatus::Running, 'tokens_used' => 300]);
        $this->mission(['status' => AiMissionStatus::Done, 'schedule' => 'daily']);
        $this->mission(['status' => AiMissionStatus::Draft]);
        AiMission::factory()->create(['status' => AiMissionStatus::WaitingApproval]);

        $tiles = app(MissionDashboard::class)->build($this->admin)['tiles'];

        $this->assertSame(1, $tiles['waiting']);
        $this->assertSame(2, $tiles['problems']);
        $this->assertSame(1, $tiles['running']);
        $this->assertSame(1, $tiles['recurring']);
    }

    public function test_this_months_tokens_come_from_finished_runs_and_the_run_in_progress(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-21 10:00', 'Asia/Tehran'));
        $done = $this->mission();
        AiMissionRun::factory()->create(['ai_mission_id' => $done->id, 'tokens_used' => 1000, 'finished_at' => now()->subDay()]);
        AiMissionRun::factory()->create(['ai_mission_id' => $done->id, 'tokens_used' => 5000, 'finished_at' => now()->subMonths(2)]);
        $this->mission(['status' => AiMissionStatus::Running, 'tokens_used' => 250]);

        $this->assertSame(1250, app(MissionDashboard::class)->build($this->admin)['tiles']['tokens_month']);
    }

    public function test_attention_lists_decisions_first_then_problems_with_their_reason(): void
    {
        $failed = $this->mission(['title' => 'اجرای ناموفق', 'status' => AiMissionStatus::Failed, 'last_error' => 'سقف توکن پر شد']);
        $waiting = $this->mission(['title' => 'منتظر تصمیم', 'status' => AiMissionStatus::WaitingApproval, 'report' => ['summary' => 'x', 'decisions' => [['title' => 'a', 'detail' => ''], ['title' => 'b', 'detail' => '']], 'uncertain' => []]]);
        $this->mission(['status' => AiMissionStatus::Done]);

        $attention = app(MissionDashboard::class)->build($this->admin)['attention'];

        $this->assertSame([$waiting->id, $failed->id], array_column($attention, 'id'));
        $this->assertSame('2 پیشنهاد منتظر نظر شماست', $attention[0]['detail']);
        $this->assertSame('سقف توکن پر شد', $attention[1]['detail']);
        $this->assertSame('warn', $attention[0]['tone']);
        $this->assertSame('bad', $attention[1]['tone']);
    }

    public function test_running_and_recurring_missions_show_progress_and_the_schedule(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-21 10:00', 'Asia/Tehran'));
        $running = $this->mission(['status' => AiMissionStatus::Running, 'current_step' => '«دستیار» در حال بررسی…', 'step_index' => 1, 'step_total' => 4]);
        $recurring = $this->mission(['schedule' => 'weekly', 'schedule_time' => '08:00', 'schedule_weekday' => 6, 'next_run_at' => app(MissionSchedule::class)->nextRun('weekly', '08:00', 6)]);

        $dashboard = app(MissionDashboard::class)->build($this->admin);

        $this->assertSame([['id' => $running->id, 'title' => $running->title, 'step' => '«دستیار» در حال بررسی…', 'percent' => 25]], $dashboard['running']);
        $this->assertSame($recurring->id, $dashboard['recurring'][0]['id']);
        $this->assertStringContainsString('هر هفته', $dashboard['recurring'][0]['every']);
        $this->assertStringContainsString('شنبه', $dashboard['recurring'][0]['every']);
        $this->assertNotNull($dashboard['recurring'][0]['next']);
    }

    public function test_recent_results_are_newest_first_and_limited_to_six(): void
    {
        $mission = $this->mission(['title' => 'مأموریت نتیجه‌دار']);
        foreach (range(1, 8) as $day) {
            AiMissionRun::factory()->create(['ai_mission_id' => $mission->id, 'summary' => "نتیجهٔ {$day}", 'finished_at' => now()->subDays(10 - $day), 'trigger' => $day === 8 ? 'scheduled' : 'manual', 'report' => ['summary' => 'x', 'decisions' => $day === 8 ? [['title' => 't', 'detail' => '']] : [], 'uncertain' => []]]);
        }

        $recent = app(MissionDashboard::class)->build($this->admin)['recent'];

        $this->assertCount(6, $recent);
        $this->assertSame('نتیجهٔ 8', $recent[0]['summary']);
        $this->assertTrue($recent[0]['scheduled']);
        $this->assertSame(1, $recent[0]['decisions']);
        $this->assertSame('مأموریت نتیجه‌دار', $recent[0]['title']);
    }

    public function test_someone_elses_missions_and_runs_never_appear(): void
    {
        $foreign = AiMission::factory()->create(['title' => 'مأموریت دیگری', 'status' => AiMissionStatus::WaitingApproval, 'schedule' => 'daily']);
        AiMissionRun::factory()->create(['ai_mission_id' => $foreign->id]);

        $dashboard = app(MissionDashboard::class)->build($this->admin);

        $this->assertSame([], $dashboard['attention']);
        $this->assertSame([], $dashboard['recurring']);
        $this->assertSame([], $dashboard['recent']);
        $this->assertSame(0, $dashboard['tiles']['waiting']);
    }

    public function test_the_landing_page_shows_the_dashboard_and_opens_a_mission_from_it(): void
    {
        $waiting = $this->mission(['title' => 'مأموریت منتظر شما', 'status' => AiMissionStatus::WaitingApproval, 'report' => ['summary' => 'x', 'decisions' => [['title' => 'a', 'detail' => '']], 'uncertain' => []]]);

        Livewire::test(AiMissionRoom::class)
            ->assertSee('نیاز به شما')
            ->assertSee('مأموریت منتظر شما')
            ->assertSee('منتظر نظر شما')
            ->call('openMission', $waiting->id)
            ->assertSet('missionId', $waiting->id)
            ->assertDontSee('نیاز به شما');
    }

    public function test_without_any_mission_the_landing_page_invites_the_first_one(): void
    {
        Livewire::test(AiMissionRoom::class)
            ->assertSee('ساخت اولین مأموریت')
            ->assertDontSee('نیاز به شما');
    }
}
