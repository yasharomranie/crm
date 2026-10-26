<?php

namespace Tests\Feature;

use App\Enums\DigitalProjectActionStatus;
use App\Enums\DigitalProjectChannel;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Filament\Resources\DigitalProjects\Pages\EditDigitalProject;
use App\Filament\Resources\DigitalProjects\Pages\ListDigitalProjects;
use App\Filament\Resources\DigitalProjects\Pages\ViewDigitalProject;
use App\Filament\Resources\DigitalProjects\RelationManagers\ActionsRelationManager;
use App\Filament\Widgets\DigitalProjectsStatsWidget;
use App\Models\DigitalProject;
use App\Models\DigitalProjectAction;
use App\Models\SeoProject;
use App\Models\SeoSiteCheck;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Digital projects (phase 4): the website's uptime and certificate from the
 * SEO centre shown next to the project, and an action turned into a CRM task.
 */
class DigitalProjectIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    private function projectWithSite(array $check = []): DigitalProject
    {
        $seo = SeoProject::factory()->create();
        SeoSiteCheck::factory()->create(['seo_project_id' => $seo->id, ...$check]);

        return DigitalProject::factory()->create(['channel' => DigitalProjectChannel::Website, 'seo_project_id' => $seo->id]);
    }

    public function test_a_healthy_site_reads_as_available(): void
    {
        $health = $this->projectWithSite()->siteHealth();

        $this->assertSame('در دسترس', $health['label']);
        $this->assertSame('success', $health['color']);
    }

    public function test_a_down_site_is_flagged_in_red(): void
    {
        $health = $this->projectWithSite(['is_up' => false])->siteHealth();

        $this->assertSame('از دسترس خارج است', $health['label']);
        $this->assertSame('danger', $health['color']);
    }

    public function test_a_certificate_close_to_expiry_warns_and_an_expired_one_is_red(): void
    {
        $soon = $this->projectWithSite(['ssl_expires_at' => now()->addDays(5)])->siteHealth();
        $expired = $this->projectWithSite(['ssl_expires_at' => now()->subDays(2)])->siteHealth();

        $this->assertSame('warning', $soon['color']);
        $this->assertStringContainsString('SSL', $soon['label']);
        $this->assertSame('SSL منقضی شده', $expired['label']);
        $this->assertSame('danger', $expired['color']);
    }

    public function test_a_project_without_a_link_or_a_reading_has_no_site_state(): void
    {
        $unlinked = DigitalProject::factory()->create();
        $seo = SeoProject::factory()->create();
        $unchecked = DigitalProject::factory()->create(['seo_project_id' => $seo->id]);

        $this->assertNull($unlinked->siteHealth());
        $this->assertNull($unchecked->siteHealth());
    }

    public function test_the_problem_filter_and_stat_count_only_linked_sites_in_trouble(): void
    {
        $down = $this->projectWithSite(['is_up' => false]);
        $expiring = $this->projectWithSite(['ssl_expires_at' => now()->addDays(3)]);
        $healthy = $this->projectWithSite();
        $unlinked = DigitalProject::factory()->create();

        $this->assertEqualsCanonicalizing([$down->id, $expiring->id], DigitalProject::query()->withSiteProblem()->pluck('id')->all());

        Livewire::actingAs($this->admin())
            ->test(ListDigitalProjects::class)
            ->filterTable('site_problem', true)
            ->assertCanSeeTableRecords([$down, $expiring])
            ->assertCanNotSeeTableRecords([$healthy, $unlinked]);

        Livewire::actingAs($this->admin())
            ->test(DigitalProjectsStatsWidget::class)
            ->assertSee('سایت دارای مشکل');
    }

    public function test_the_table_shows_the_site_state(): void
    {
        $this->projectWithSite(['is_up' => false]);

        Livewire::actingAs($this->admin())
            ->test(ListDigitalProjects::class)
            ->assertSee('از دسترس خارج است');
    }

    public function test_a_website_project_can_be_linked_to_its_seo_project(): void
    {
        $seo = SeoProject::factory()->create();
        $project = DigitalProject::factory()->create(['channel' => DigitalProjectChannel::Website]);

        Livewire::actingAs($this->admin())
            ->test(EditDigitalProject::class, ['record' => $project->getRouteKey()])
            ->fillForm(['seo_project_id' => $seo->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($seo->id, $project->refresh()->seo_project_id);
    }

    public function test_the_project_page_shows_the_site_state_only_when_linked(): void
    {
        $linked = $this->projectWithSite(['is_up' => false]);
        $plain = DigitalProject::factory()->create(['name' => 'پیج اینستاگرام ساده']);

        Livewire::actingAs($this->admin())
            ->test(ViewDigitalProject::class, ['record' => $linked->getRouteKey()])
            ->assertSee('وضعیت سایت')
            ->assertSee('از دسترس خارج است');

        Livewire::actingAs($this->admin())
            ->test(ViewDigitalProject::class, ['record' => $plain->getRouteKey()])
            ->assertDontSee('وضعیت سایت');
    }

    public function test_an_action_becomes_a_crm_task_with_the_same_details(): void
    {
        $admin = $this->admin();
        $assignee = User::factory()->create();
        $project = DigitalProject::factory()->create(['name' => 'سایت اصلی']);
        $action = DigitalProjectAction::factory()->create([
            'digital_project_id' => $project->id,
            'title' => 'تمدید گواهی SSL',
            'details' => 'از پنل هاست',
            'assigned_to' => $assignee->id,
            'due_on' => now()->addDays(3),
        ]);

        Livewire::actingAs($admin)
            ->test(ActionsRelationManager::class, ['ownerRecord' => $project, 'pageClass' => ViewDigitalProject::class])
            ->callTableAction('toTask', $action);

        $task = Task::firstOrFail();

        $this->assertSame('سایت اصلی: تمدید گواهی SSL', $task->title);
        $this->assertSame('از پنل هاست', $task->description);
        $this->assertSame($assignee->id, $task->assigned_to);
        $this->assertSame($admin->id, $task->assigned_by);
        $this->assertSame(TaskType::Manual, $task->task_type);
        $this->assertSame(TaskStatus::Todo, $task->status);
        $this->assertTrue($task->due_at->isSameDay(now()->addDays(3)));
        $this->assertTrue($action->refresh()->task->is($task));
    }

    public function test_an_action_is_converted_only_once(): void
    {
        $action = DigitalProjectAction::factory()->create();

        $first = $action->convertToTask(null);
        $second = $action->fresh()->convertToTask(null);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Task::count());
    }

    public function test_the_convert_button_hides_for_finished_or_already_converted_actions(): void
    {
        $project = DigitalProject::factory()->create();
        $open = DigitalProjectAction::factory()->create(['digital_project_id' => $project->id]);
        $done = DigitalProjectAction::factory()->create(['digital_project_id' => $project->id, 'status' => DigitalProjectActionStatus::Done]);
        $converted = DigitalProjectAction::factory()->create(['digital_project_id' => $project->id]);
        $converted->convertToTask(null);

        Livewire::actingAs($this->admin())
            ->test(ActionsRelationManager::class, ['ownerRecord' => $project, 'pageClass' => ViewDigitalProject::class])
            ->assertTableActionVisible('toTask', $open)
            ->assertTableActionHidden('toTask', $done)
            ->assertTableActionHidden('toTask', $converted);
    }
}
