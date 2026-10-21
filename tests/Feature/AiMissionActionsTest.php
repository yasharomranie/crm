<?php

namespace Tests\Feature;

use App\Domain\Ai\Missions\MissionActionExecutor;
use App\Domain\Ai\Missions\MissionRunner;
use App\Enums\AiMissionActionStatus;
use App\Enums\AiMissionRoleType;
use App\Enums\AiMissionStatus;
use App\Filament\Pages\AiMissionRoom;
use App\Models\AiAssistantProfile;
use App\Models\AiMission;
use App\Models\AiMissionAction;
use App\Models\AiMissionRole;
use App\Models\AiProvider;
use App\Models\Department;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The team may only propose changes; each one waits in the approval queue and
 * runs once, for the mission's owner, after that person approves it.
 */
class AiMissionActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['name' => 'مدیر آزمایشی']);
        $this->admin->assignRole(Role::firstOrCreate(['name' => 'admin']));
        $this->actingAs($this->admin);
        AiProvider::factory()->active()->create();
    }

    private function missionWithTeam(): AiMission
    {
        $mission = AiMission::factory()->create(['created_by' => $this->admin->id, 'goal' => 'آماده‌سازی هفتهٔ آینده']);

        foreach ([AiMissionRoleType::Specialist, AiMissionRoleType::Coordinator] as $type) {
            AiMissionRole::factory()->create([
                'ai_mission_id' => $mission->id,
                'ai_assistant_profile_id' => AiAssistantProfile::factory()->create(['created_by' => $this->admin->id])->id,
                'role' => $type,
            ]);
        }

        return $mission;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function fakeTeam(array $report): void
    {
        Http::fake(['*/chat/completions' => function (Request $request) use ($report) {
            $isCoordinator = str_contains($request->data()['messages'][0]['content'], 'نقش «هماهنگ‌کننده»');
            $content = $isCoordinator ? $report : ['findings' => []];

            return Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => json_encode($content, JSON_UNESCAPED_UNICODE)]]]]);
        }]);
    }

    public function test_proposed_actions_wait_in_the_queue_and_change_nothing(): void
    {
        $mission = $this->missionWithTeam();
        $this->fakeTeam(['summary' => 'خلاصه', 'decisions' => [], 'uncertain' => [], 'actions' => [
            ['tool' => 'create_task', 'title' => 'تماس با مهمانان گروهی', 'priority' => 'urgent_important', 'reason' => 'ورود گروه فردا'],
            ['tool' => 'create_task', 'title' => 'تماس با مهمانان گروهی'],
            ['tool' => 'delete_everything', 'title' => 'خطرناک'],
            ['tool' => 'create_task', 'title' => '   '],
        ]]);

        $result = app(MissionRunner::class)->runRound($mission, $this->admin);

        $this->assertTrue($result['ok']);
        $this->assertSame(0, Task::count());
        $action = AiMissionAction::sole();
        $this->assertSame(AiMissionActionStatus::Pending, $action->status);
        $this->assertSame('تماس با مهمانان گروهی', $action->arguments['title']);
        $this->assertSame('urgent_important', $action->arguments['priority']);
        $this->assertSame(AiMissionStatus::WaitingApproval, $mission->fresh()->status);
    }

    public function test_an_action_already_waiting_is_not_proposed_again_in_the_next_round(): void
    {
        $mission = $this->missionWithTeam();
        $mission->update(['max_rounds' => 3]);
        $this->fakeTeam(['summary' => 'خلاصه', 'decisions' => [], 'actions' => [['tool' => 'create_task', 'title' => 'پیگیری پرداخت‌ها']]]);

        app(MissionRunner::class)->runRound($mission, $this->admin);
        app(MissionRunner::class)->runRound($mission->fresh(), $this->admin);

        $this->assertSame(1, AiMissionAction::count());
    }

    public function test_an_owner_who_may_not_act_gets_no_proposals(): void
    {
        $this->artisan('access-control:sync');
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'فقط گزارش']));

        $this->assertNull(app(MissionActionExecutor::class)->sanitize(['tool' => 'create_task', 'title' => 'کاری'], $user));
        $this->assertNotNull(app(MissionActionExecutor::class)->sanitize(['tool' => 'create_task', 'title' => 'کاری'], $this->admin));
    }

    public function test_approving_creates_the_task_once(): void
    {
        $department = Department::factory()->create(['name' => 'پذیرش']);
        $action = AiMissionAction::factory()->create([
            'ai_mission_id' => $this->missionWithTeam()->id,
            'arguments' => ['title' => 'آماده‌سازی اتاق‌ها', 'department_name' => 'پذیرش', 'priority' => 'urgent_not_important'],
        ]);

        Livewire::test(AiMissionRoom::class)
            ->call('approveAction', $action->id)
            ->call('approveAction', $action->id);

        $task = Task::sole();
        $this->assertSame('آماده‌سازی اتاق‌ها', $task->title);
        $this->assertSame($department->id, $task->department_id);
        $action->refresh();
        $this->assertSame(AiMissionActionStatus::Executed, $action->status);
        $this->assertSame($task->id, $action->result['task_id']);
        $this->assertSame($this->admin->id, $action->decided_by);
    }

    public function test_a_failing_action_is_marked_failed_with_the_reason(): void
    {
        $action = AiMissionAction::factory()->create([
            'ai_mission_id' => $this->missionWithTeam()->id,
            'arguments' => ['title' => 'کار', 'department_name' => 'دپارتمان ناموجود'],
        ]);

        Livewire::test(AiMissionRoom::class)->call('approveAction', $action->id);

        $action->refresh();
        $this->assertSame(AiMissionActionStatus::Failed, $action->status);
        $this->assertStringContainsString('دپارتمان ناموجود', $action->error);
        $this->assertSame(0, Task::count());
    }

    public function test_rejecting_does_nothing_to_the_data(): void
    {
        $action = AiMissionAction::factory()->create(['ai_mission_id' => $this->missionWithTeam()->id]);

        Livewire::test(AiMissionRoom::class)->call('rejectAction', $action->id);

        $this->assertSame(AiMissionActionStatus::Rejected, $action->fresh()->status);
        $this->assertSame(0, Task::count());
    }

    public function test_only_the_missions_owner_can_decide_on_its_actions(): void
    {
        $action = AiMissionAction::factory()->create(['ai_mission_id' => $this->missionWithTeam()->id]);
        $other = User::factory()->create();
        $other->assignRole(Role::firstOrCreate(['name' => 'admin']));

        Livewire::actingAs($other)->test(AiMissionRoom::class)
            ->call('approveAction', $action->id)
            ->call('rejectAction', $action->id);

        $this->assertSame(AiMissionActionStatus::Pending, $action->fresh()->status);
        $this->assertSame(0, Task::count());
    }

    public function test_permission_is_checked_again_when_approving(): void
    {
        $this->artisan('access-control:sync');
        $owner = User::factory()->create();
        $owner->assignRole(Role::create(['name' => 'بدون اقدام']));
        $mission = AiMission::factory()->create(['created_by' => $owner->id]);
        $action = AiMissionAction::factory()->create(['ai_mission_id' => $mission->id]);

        $action = app(MissionActionExecutor::class)->approve($action, $owner);

        $this->assertSame(AiMissionActionStatus::Failed, $action->status);
        $this->assertSame(0, Task::count());
    }

    public function test_the_queue_lists_pending_actions_and_the_dashboard_counts_them(): void
    {
        $mission = AiMission::factory()->create(['created_by' => $this->admin->id, 'title' => 'مأموریت صف']);
        AiMissionAction::factory()->create(['ai_mission_id' => $mission->id, 'summary' => 'ایجاد وظیفهٔ «پیگیری چک‌این»']);
        AiMissionAction::factory()->create(['ai_mission_id' => $mission->id, 'status' => AiMissionActionStatus::Rejected, 'summary' => 'ایجاد وظیفهٔ «ردشده»']);
        AiMissionAction::factory()->create(['summary' => 'ایجاد وظیفهٔ «متعلق به دیگری»']);

        Livewire::test(AiMissionRoom::class)
            ->assertSee('صف تأیید اقدام‌ها')
            ->assertSee('پیگیری چک‌این')
            ->assertDontSee('ردشده')
            ->assertDontSee('متعلق به دیگری');
    }
}
