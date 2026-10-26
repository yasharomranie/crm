<?php

namespace Tests\Feature;

use App\Enums\DigitalProjectActionStatus;
use App\Enums\DigitalProjectLogKind;
use App\Enums\DigitalProjectStatus;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\DigitalProjectLogs\Pages\ManageDigitalProjectLogs;
use App\Filament\Resources\DigitalProjects\Pages\ListDigitalProjects;
use App\Filament\Resources\DigitalProjects\Pages\ViewDigitalProject;
use App\Filament\Resources\DigitalProjects\RelationManagers\ActionsRelationManager;
use App\Filament\Resources\DigitalProjects\RelationManagers\LogsRelationManager;
use App\Filament\Resources\DigitalProjects\RelationManagers\StatusChangesRelationManager;
use App\Filament\Widgets\DigitalProjectQuickLogWidget;
use App\Filament\Widgets\DigitalProjectsStatsWidget;
use App\Models\DigitalProject;
use App\Models\DigitalProjectAction;
use App\Models\DigitalProjectLog;
use App\Models\DigitalProjectStatusChange;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Digital projects (phase 2): actions per project, the free daily journal,
 * the automatic status history and the "no report for a week" signal.
 */
class DigitalProjectActivityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_the_daily_journal_module_has_its_own_permissions(): void
    {
        $this->artisan('access-control:sync');

        foreach (['view_any', 'create', 'update', 'delete'] as $action) {
            $this->assertTrue(Permission::where('name', "digital-project-logs.{$action}")->exists(), $action);
        }
    }

    public function test_every_status_change_is_recorded_with_who_and_from_to(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $project = DigitalProject::factory()->create(['status' => DigitalProjectStatus::Planning]);

        $project->update(['status' => DigitalProjectStatus::Active]);
        $project->update(['name' => 'نام تازه']);
        $project->update(['status' => DigitalProjectStatus::Paused]);

        $changes = $project->statusChanges()->orderBy('id')->get();

        $this->assertCount(3, $changes);
        $this->assertNull($changes[0]->from_status);
        $this->assertSame(DigitalProjectStatus::Planning, $changes[0]->to_status);
        $this->assertSame(DigitalProjectStatus::Planning, $changes[1]->from_status);
        $this->assertSame(DigitalProjectStatus::Active, $changes[1]->to_status);
        $this->assertSame(DigitalProjectStatus::Paused, $changes[2]->to_status);
        $this->assertSame($admin->id, $changes[2]->changed_by);
    }

    public function test_the_change_status_button_stores_the_reason(): void
    {
        $project = DigitalProject::factory()->create(['status' => DigitalProjectStatus::Active]);

        Livewire::actingAs($this->admin())
            ->test(ViewDigitalProject::class, ['record' => $project->getRouteKey()])
            ->callAction('changeStatus', ['status' => DigitalProjectStatus::NeedsAttention->value, 'note' => 'گواهی SSL در حال انقضاست'])
            ->assertHasNoActionErrors();

        $change = $project->statusChanges()->orderByDesc('id')->firstOrFail();

        $this->assertSame(DigitalProjectStatus::NeedsAttention, $project->refresh()->status);
        $this->assertSame('گواهی SSL در حال انقضاست', $change->note);
        $this->assertSame(DigitalProjectStatus::Active, $change->from_status);
    }

    public function test_the_history_tab_lists_the_changes(): void
    {
        $project = DigitalProject::factory()->create(['status' => DigitalProjectStatus::Planning]);
        $project->update(['status' => DigitalProjectStatus::Active]);

        Livewire::actingAs($this->admin())
            ->test(StatusChangesRelationManager::class, ['ownerRecord' => $project, 'pageClass' => ViewDigitalProject::class])
            ->assertCanSeeTableRecords($project->statusChanges()->get());
    }

    public function test_an_action_is_added_to_a_project_and_finishing_it_stamps_the_time(): void
    {
        $admin = $this->admin();
        $project = DigitalProject::factory()->create();

        Livewire::actingAs($admin)
            ->test(ActionsRelationManager::class, ['ownerRecord' => $project, 'pageClass' => ViewDigitalProject::class])
            ->mountTableAction('create')
            ->setTableActionData(['title' => 'تمدید گواهی SSL', 'status' => DigitalProjectActionStatus::Open->value, 'due_on' => now()->addDays(3)->toDateString()])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $action = $project->actions()->firstOrFail();

        $this->assertSame('تمدید گواهی SSL', $action->title);
        $this->assertSame($admin->id, $action->created_by);
        $this->assertNull($action->completed_at);

        Livewire::actingAs($admin)
            ->test(ActionsRelationManager::class, ['ownerRecord' => $project, 'pageClass' => ViewDigitalProject::class])
            ->callTableAction('finish', $action);

        $action->refresh();

        $this->assertSame(DigitalProjectActionStatus::Done, $action->status);
        $this->assertNotNull($action->completed_at);

        $action->update(['status' => DigitalProjectActionStatus::InProgress]);

        $this->assertNull($action->refresh()->completed_at);
    }

    public function test_only_unfinished_actions_with_a_passed_deadline_are_overdue(): void
    {
        $late = DigitalProjectAction::factory()->create(['due_on' => now()->subDay()]);
        $done = DigitalProjectAction::factory()->create(['due_on' => now()->subDay(), 'status' => DigitalProjectActionStatus::Done]);
        $future = DigitalProjectAction::factory()->create(['due_on' => now()->addDay()]);

        $this->assertTrue($late->isOverdue());
        $this->assertFalse($done->isOverdue());
        $this->assertFalse($future->isOverdue());
    }

    public function test_a_journal_line_is_written_from_the_project_page(): void
    {
        $admin = $this->admin();
        $project = DigitalProject::factory()->create();

        Livewire::actingAs($admin)
            ->test(LogsRelationManager::class, ['ownerRecord' => $project, 'pageClass' => ViewDigitalProject::class])
            ->mountTableAction('create')
            ->setTableActionData(['logged_on' => today()->toDateString(), 'kind' => DigitalProjectLogKind::Problem->value, 'body' => 'پلاگین کش خطا می‌دهد'])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $log = $project->logs()->firstOrFail();

        $this->assertSame(DigitalProjectLogKind::Problem, $log->kind);
        $this->assertSame($admin->id, $log->user_id);
    }

    public function test_the_quick_entry_writes_todays_line_for_the_chosen_project(): void
    {
        $admin = $this->admin();
        $project = DigitalProject::factory()->create();

        Livewire::actingAs($admin)
            ->test(DigitalProjectQuickLogWidget::class)
            ->set('projectId', $project->id)
            ->set('kind', DigitalProjectLogKind::Idea->value)
            ->set('body', 'یک ریلز معرفی اتاق‌ها بسازیم')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('body', '');

        $log = DigitalProjectLog::firstOrFail();

        $this->assertSame($project->id, $log->digital_project_id);
        $this->assertSame(DigitalProjectLogKind::Idea, $log->kind);
        $this->assertTrue($log->logged_on->isToday());
        $this->assertSame($admin->id, $log->user_id);
    }

    public function test_the_quick_entry_needs_a_project_and_text(): void
    {
        Livewire::actingAs($this->admin())
            ->test(DigitalProjectQuickLogWidget::class)
            ->call('save')
            ->assertHasErrors(['projectId' => 'required', 'body' => 'required']);

        $this->assertSame(0, DigitalProjectLog::count());
    }

    public function test_the_table_shows_the_latest_report_and_the_open_action_count(): void
    {
        $project = DigitalProject::factory()->create();
        DigitalProjectLog::factory()->create(['digital_project_id' => $project->id, 'body' => 'گزارش قدیمی', 'logged_on' => now()->subDays(3)]);
        DigitalProjectLog::factory()->create(['digital_project_id' => $project->id, 'body' => 'گزارش تازه‌ترین', 'logged_on' => today()]);
        DigitalProjectAction::factory()->count(2)->create(['digital_project_id' => $project->id]);
        DigitalProjectAction::factory()->create(['digital_project_id' => $project->id, 'status' => DigitalProjectActionStatus::Done]);

        Livewire::actingAs($this->admin())
            ->test(ListDigitalProjects::class)
            ->assertSee('گزارش تازه‌ترین')
            ->assertDontSee('گزارش قدیمی')
            ->assertTableColumnStateSet('open_actions_count', 2, $project);
    }

    public function test_open_projects_without_a_report_in_a_week_are_flagged(): void
    {
        $stale = DigitalProject::factory()->create(['created_at' => now()->subDays(20)]);
        $brandNew = DigitalProject::factory()->create();
        $recent = DigitalProject::factory()->create(['created_at' => now()->subDays(20)]);
        DigitalProjectLog::factory()->create(['digital_project_id' => $recent->id, 'logged_on' => now()->subDays(2)]);
        $oldLog = DigitalProject::factory()->create(['created_at' => now()->subDays(30)]);
        DigitalProjectLog::factory()->create(['digital_project_id' => $oldLog->id, 'logged_on' => now()->subDays(10)]);
        $paused = DigitalProject::factory()->create(['status' => DigitalProjectStatus::Paused, 'created_at' => now()->subDays(20)]);

        $ids = DigitalProject::query()->withoutRecentLog()->pluck('id')->sort()->values()->all();

        $this->assertSame(collect([$stale->id, $oldLog->id])->sort()->values()->all(), $ids);
        $this->assertNotContains($brandNew->id, $ids);
        $this->assertNotContains($paused->id, $ids);

        Livewire::actingAs($this->admin())
            ->test(ListDigitalProjects::class)
            ->filterTable('without_recent_log', true)
            ->assertCanSeeTableRecords([$stale, $oldLog])
            ->assertCanNotSeeTableRecords([$brandNew, $recent, $paused]);
    }

    public function test_the_stats_include_the_unattended_count(): void
    {
        DigitalProject::factory()->create(['created_at' => now()->subDays(20)]);

        Livewire::actingAs($this->admin())
            ->test(DigitalProjectsStatsWidget::class)
            ->assertSee('بدون گزارش بیش از ۷ روز');
    }

    public function test_the_global_journal_lists_every_line_and_filters_by_project(): void
    {
        $first = DigitalProjectLog::factory()->create();
        $second = DigitalProjectLog::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ManageDigitalProjectLogs::class)
            ->assertCanSeeTableRecords([$first, $second])
            ->filterTable('digital_project_id', $first->digital_project_id)
            ->assertCanSeeTableRecords([$first])
            ->assertCanNotSeeTableRecords([$second]);
    }

    public function test_deleting_a_project_removes_its_actions_logs_and_history(): void
    {
        $project = DigitalProject::factory()->create();
        DigitalProjectAction::factory()->create(['digital_project_id' => $project->id]);
        DigitalProjectLog::factory()->create(['digital_project_id' => $project->id]);

        $project->delete();

        $this->assertSame(0, DigitalProjectAction::count());
        $this->assertSame(0, DigitalProjectLog::count());
        $this->assertSame(0, DigitalProjectStatusChange::count());
    }

    public function test_the_quick_entry_stays_off_the_main_dashboard(): void
    {
        $this->actingAs($this->admin())->get(Dashboard::getUrl())->assertOk()->assertDontSee('امروز چه کار کردی؟');
    }
}
