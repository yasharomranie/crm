<?php

namespace Tests\Feature;

use App\Enums\AiMessageRole;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Filament\Pages\AiAssistant;
use App\Models\AiConversation;
use App\Models\AiProvider;
use App\Models\Project;
use App\Models\ProjectExpense;
use App\Models\ProjectPhase;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Covers the eight tasks/projects reporting tools added on top of the
 * pre-existing get_task_summary and get_project_health_summary: project
 * drill-down, overdue WBS phases, budget vs actual spend, project
 * expenses, task search, named overdue tasks, per-assignee workload, and
 * the Eisenhower priority breakdown.
 */
class AiAssistantTaskProjectToolsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsFullyPermittedUser(): User
    {
        $this->artisan('access-control:sync');

        $role = Role::create(['name' => 'مدیر کامل وظایف و پروژه‌ها']);
        $role->givePermissionTo(Permission::whereIn('name', [
            'ai-assistant.access', 'ai-assistant.report',
        ])->orWhere('name', 'like', '%.view_any')->orWhere('name', 'tasks.create')->get());

        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function fakeToolCallThenAnswer(string $toolName, array $arguments, string $finalAnswer = 'باشه.'): void
    {
        Http::fakeSequence()
            ->push([
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_1',
                            'type' => 'function',
                            'function' => ['name' => $toolName, 'arguments' => json_encode($arguments)],
                        ]],
                    ],
                ]],
            ])
            ->push([
                'choices' => [[
                    'message' => ['role' => 'assistant', 'content' => $finalAnswer],
                ]],
            ]);
    }

    private function toolResultPayload(): array
    {
        $toolMessage = AiConversation::sole()->messages->firstWhere('role', AiMessageRole::Tool);

        return json_decode($toolMessage->content, true);
    }

    public function test_project_detail_reports_budget_actual_cost_and_critical_path_order(): void
    {
        AiProvider::factory()->active()->create();
        $owner = User::factory()->create(['name' => 'مدیر پروژه']);
        $project = Project::create([
            'code' => 'PRJ-1', 'title' => 'سایت فروشگاهی', 'owner_id' => $owner->id, 'budget' => 10_000_000,
        ]);

        $first = ProjectPhase::create([
            'project_id' => $project->id, 'title' => 'طراحی', 'weight' => 50, 'progress_percent' => 100,
            'start_date' => today()->subDays(10), 'end_date' => today()->subDays(5),
        ]);
        ProjectPhase::create([
            'project_id' => $project->id, 'title' => 'توسعه', 'weight' => 50, 'progress_percent' => 20,
            'predecessor_id' => $first->id, 'start_date' => today()->subDays(4), 'end_date' => today()->addDays(5),
        ]);

        ProjectExpense::create([
            'project_id' => $project->id, 'title' => 'هاست و دامنه', 'amount' => 3_000_000, 'spent_at' => today(),
        ]);

        $this->fakeToolCallThenAnswer('get_project_detail', ['project_name' => 'سایت فروشگاهی']);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'پروژه سایت فروشگاهی چطوره؟')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertSame('سایت فروشگاهی', $payload['title']);
        $this->assertSame(10_000_000, $payload['budget_toman']);
        $this->assertSame(3_000_000, $payload['actual_cost_toman']);
        $this->assertSame('مدیر پروژه', $payload['owner']);
        $this->assertSame(['طراحی', 'توسعه'], $payload['critical_path_phases']);
    }

    public function test_overdue_project_phases_flags_only_the_leaf_phase_past_its_planned_end(): void
    {
        AiProvider::factory()->active()->create();
        $project = Project::create(['code' => 'PRJ-2', 'title' => 'بازسازی لابی']);

        ProjectPhase::create([
            'project_id' => $project->id, 'title' => 'خرید مصالح', 'weight' => 50, 'progress_percent' => 40,
            'start_date' => today()->subDays(10), 'end_date' => today()->subDays(3),
        ]);
        // On schedule - must not appear.
        ProjectPhase::create([
            'project_id' => $project->id, 'title' => 'اجرا', 'weight' => 50, 'progress_percent' => 10,
            'start_date' => today(), 'end_date' => today()->addDays(10),
        ]);

        $this->fakeToolCallThenAnswer('get_overdue_project_phases', []);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'کدام فاز پروژه عقب افتاده؟')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertSame(1, $payload['overdue_count']);
        $this->assertSame('خرید مصالح', $payload['phases'][0]['phase']);
        $this->assertSame('بازسازی لابی', $payload['phases'][0]['project']);
        $this->assertSame(3, $payload['phases'][0]['days_overdue']);
    }

    public function test_project_budget_summary_computes_percent_spent(): void
    {
        AiProvider::factory()->active()->create();
        $project = Project::create(['code' => 'PRJ-3', 'title' => 'کمپین تبلیغاتی', 'budget' => 10_000_000]);
        ProjectExpense::create(['project_id' => $project->id, 'title' => 'چاپ بنر', 'amount' => 2_500_000, 'spent_at' => today()]);

        $this->fakeToolCallThenAnswer('get_project_budget_summary', []);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'کدام پروژه داره از بودجه رد میشه؟')
            ->call('send');

        $payload = $this->toolResultPayload();
        $row = collect($payload['projects'])->firstWhere('title', 'کمپین تبلیغاتی');

        $this->assertSame(10_000_000, $row['budget_toman']);
        $this->assertSame(2_500_000, $row['spent_toman']);
        $this->assertSame(7_500_000, $row['remaining_toman']);
        $this->assertEquals(25.0, $row['percent_spent']);
    }

    public function test_project_expenses_lists_a_specific_projects_spend(): void
    {
        AiProvider::factory()->active()->create();
        $project = Project::create(['code' => 'PRJ-4', 'title' => 'ارتقای سرور']);
        ProjectExpense::create(['project_id' => $project->id, 'title' => 'خرید SSD', 'amount' => 1_200_000, 'spent_at' => today()]);
        ProjectExpense::create(['project_id' => $project->id, 'title' => 'نصب و راه‌اندازی', 'amount' => 800_000, 'spent_at' => today()->subDay()]);

        $this->fakeToolCallThenAnswer('get_project_expenses', ['project_name' => 'ارتقای سرور']);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'پروژه ارتقای سرور رو چی خرج کرده؟')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertSame(2_000_000, $payload['total_spent_toman']);
        $this->assertCount(2, $payload['expenses']);
    }

    public function test_search_tasks_filters_by_assignee_and_status(): void
    {
        AiProvider::factory()->active()->create();
        $user = User::factory()->create(['name' => 'نیلوفر احمدی']);
        $openTask = Task::factory()->create(['assigned_to' => $user->id, 'status' => TaskStatus::InProgress]);
        Task::factory()->create(['assigned_to' => $user->id, 'status' => TaskStatus::Done]);
        Task::factory()->create(); // a different, unrelated assignee

        $this->fakeToolCallThenAnswer('search_tasks', ['assignee_name' => 'نیلوفر', 'status' => 'in_progress']);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'کارهای در حال انجام نیلوفر رو نشون بده')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertSame(1, $payload['count']);
        $this->assertSame($openTask->title, $payload['tasks'][0]['title']);
        $this->assertSame('نیلوفر احمدی', $payload['tasks'][0]['assignee']);
    }

    public function test_overdue_tasks_lists_the_assignees_name_and_how_late_it_is(): void
    {
        AiProvider::factory()->active()->create();
        $user = User::factory()->create(['name' => 'حسین مرادی']);
        Task::factory()->create([
            'assigned_to' => $user->id,
            'status' => TaskStatus::InProgress,
            'due_at' => now()->subDays(3),
        ]);

        $this->fakeToolCallThenAnswer('get_overdue_tasks', []);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'کدام وظیفه عقب افتاده؟')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertSame(1, $payload['overdue_count']);
        $this->assertSame('حسین مرادی', $payload['tasks'][0]['assignee']);
        $this->assertSame(3, $payload['tasks'][0]['days_overdue']);
    }

    public function test_tasks_by_assignee_separates_open_overdue_and_completed_this_month(): void
    {
        AiProvider::factory()->active()->create();
        $user = User::factory()->create(['name' => 'کارمند تست']);

        Task::factory()->create(['assigned_to' => $user->id, 'status' => TaskStatus::Todo]);
        Task::factory()->create(['assigned_to' => $user->id, 'status' => TaskStatus::InProgress, 'due_at' => now()->subDay()]);
        Task::factory()->create([
            'assigned_to' => $user->id, 'status' => TaskStatus::Done, 'completed_at' => now(),
        ]);

        $this->fakeToolCallThenAnswer('get_tasks_by_assignee', ['user_name' => 'کارمند تست']);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'بار کاری کارمند تست چقدره؟')
            ->call('send');

        $payload = $this->toolResultPayload();
        $row = $payload['assignees'][0];

        $this->assertSame('کارمند تست', $row['user']);
        $this->assertSame(2, $row['open_count']);
        $this->assertSame(1, $row['overdue_count']);
        $this->assertSame(1, $row['completed_this_month']);
    }

    public function test_task_priority_breakdown_counts_urgent_important_and_its_overdue_subset(): void
    {
        AiProvider::factory()->active()->create();
        Task::factory()->create(['priority' => TaskPriority::UrgentImportant, 'due_at' => now()->subDay()]);
        Task::factory()->create(['priority' => TaskPriority::UrgentImportant]);
        Task::factory()->create(['priority' => TaskPriority::Neither]);

        $this->fakeToolCallThenAnswer('get_task_priority_breakdown', []);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'چقدر از کارهای فوری و مهم عقب افتاده؟')
            ->call('send');

        $payload = $this->toolResultPayload();
        $urgentImportant = collect($payload['priorities'])->firstWhere('priority', TaskPriority::UrgentImportant->getLabel());

        $this->assertSame(2, $urgentImportant['count']);
        $this->assertSame(1, $urgentImportant['overdue_count']);
    }

    public function test_a_user_without_the_report_permission_cannot_use_a_task_or_project_tool(): void
    {
        AiProvider::factory()->active()->create();
        Task::factory()->create();

        $this->fakeToolCallThenAnswer('get_overdue_tasks', []);

        // A roleless user keeps unrestricted access under this app's
        // Gate::before bypass (AppServiceProvider::registerAccessControl),
        // so the restriction only takes effect once a role is assigned.
        $this->artisan('access-control:sync');
        $role = Role::create(['name' => 'مشاهده‌گر وظایف']);
        $role->givePermissionTo(Permission::where('name', 'ai-assistant.access')->sole());
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        Livewire::test(AiAssistant::class)
            ->set('body', 'کدام وظیفه عقب افتاده؟')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertArrayHasKey('error', $payload);
    }
}
