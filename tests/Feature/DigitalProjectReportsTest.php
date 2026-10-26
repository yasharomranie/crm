<?php

namespace Tests\Feature;

use App\Domain\DigitalProjects\DigitalProjectAlertService;
use App\Enums\DigitalProjectActionStatus;
use App\Enums\DigitalProjectLogKind;
use App\Enums\DigitalProjectStatus;
use App\Filament\Resources\DigitalProjects\Pages\ListDigitalProjects;
use App\Filament\Resources\DigitalProjects\Pages\ViewDigitalProject;
use App\Models\DigitalProject;
use App\Models\DigitalProjectAction;
use App\Models\DigitalProjectLog;
use App\Models\User;
use App\Notifications\DigitalProjectsDigestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Digital projects (phase 3): Excel/PDF exports, the printable project brief
 * and weekly report, and the morning digest of projects that need attention.
 */
class DigitalProjectReportsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_the_project_brief_prints_actions_logs_and_status_history(): void
    {
        $project = DigitalProject::factory()->create(['name' => 'کانال تلگرام گنجنامه', 'description' => 'اطلاع‌رسانی روزانه']);
        DigitalProjectAction::factory()->create(['digital_project_id' => $project->id, 'title' => 'تمدید دامنه', 'due_on' => now()->subDay()]);
        DigitalProjectLog::factory()->create(['digital_project_id' => $project->id, 'body' => 'پست جدید گذاشتیم']);
        $project->update(['status' => DigitalProjectStatus::NeedsAttention]);

        $this->actingAs($this->admin())
            ->get(route('digital-projects.print', $project))
            ->assertOk()
            ->assertSee('کانال تلگرام گنجنامه')
            ->assertSee('اطلاع‌رسانی روزانه')
            ->assertSee('تمدید دامنه')
            ->assertSee('پست جدید گذاشتیم')
            ->assertSee('نیازمند توجه')
            ->assertSee('از مهلت گذشته', false);
    }

    public function test_the_weekly_report_lists_only_the_last_days_of_work_per_project(): void
    {
        $project = DigitalProject::factory()->create(['name' => 'پیج اینستاگرام هتل']);
        DigitalProjectLog::factory()->create(['digital_project_id' => $project->id, 'kind' => DigitalProjectLogKind::Problem, 'body' => 'پست منتشر نشد', 'logged_on' => now()->subDays(2)]);
        DigitalProjectLog::factory()->create(['digital_project_id' => $project->id, 'body' => 'گزارش خیلی قدیمی', 'logged_on' => now()->subDays(30)]);
        DigitalProjectAction::factory()->create(['digital_project_id' => $project->id, 'title' => 'طراحی استوری']);
        DigitalProjectAction::factory()->create(['digital_project_id' => $project->id, 'title' => 'کار تمام‌شده', 'status' => DigitalProjectActionStatus::Done]);

        $this->actingAs($this->admin())
            ->get(route('digital-projects.weekly-report'))
            ->assertOk()
            ->assertSee('پیج اینستاگرام هتل')
            ->assertSee('پست منتشر نشد')
            ->assertSee('مشکل')
            ->assertSee('طراحی استوری')
            ->assertDontSee('گزارش خیلی قدیمی')
            ->assertDontSee('کار تمام‌شده');
    }

    public function test_the_print_pages_need_the_view_permission(): void
    {
        $this->artisan('access-control:sync');
        $project = DigitalProject::factory()->create();
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'بدون دسترسی چاپ پروژه']));

        $this->actingAs($user)->get(route('digital-projects.print', $project))->assertForbidden();
        $this->actingAs($user)->get(route('digital-projects.weekly-report'))->assertForbidden();
        $this->assertTrue(Permission::where('name', 'digital-projects.view')->exists());
    }

    public function test_a_guest_is_redirected_away_from_the_print_pages(): void
    {
        $project = DigitalProject::factory()->create();

        $this->get(route('digital-projects.print', $project))->assertRedirect();
        $this->get(route('digital-projects.weekly-report'))->assertRedirect();
    }

    public function test_the_list_and_project_page_offer_exports_and_prints(): void
    {
        $project = DigitalProject::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(ListDigitalProjects::class)
            ->assertActionExists('weeklyReport')
            ->assertTableActionExists('exportExcel')
            ->assertTableActionExists('exportPdf');

        Livewire::actingAs($this->admin())
            ->test(ViewDigitalProject::class, ['record' => $project->getRouteKey()])
            ->assertActionExists('print');
    }

    public function test_the_excel_export_downloads_a_file(): void
    {
        DigitalProject::factory()->create(['name' => 'سایت اصلی']);

        Livewire::actingAs($this->admin())
            ->test(ListDigitalProjects::class)
            ->callTableAction('exportExcel')
            ->assertFileDownloaded();
    }

    public function test_an_owner_gets_one_digest_of_unattended_projects_late_reviews_and_late_actions(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $stale = DigitalProject::factory()->create(['owner_id' => $owner->id, 'created_at' => now()->subDays(20)]);
        $lateReview = DigitalProject::factory()->create(['owner_id' => $owner->id, 'next_review_at' => now()->subDays(2)]);
        DigitalProjectLog::factory()->create(['digital_project_id' => $lateReview->id, 'logged_on' => now()->subDay()]);
        DigitalProjectAction::factory()->create(['digital_project_id' => $lateReview->id, 'assigned_to' => $owner->id, 'due_on' => now()->subDay()]);

        $this->assertSame(3, app(DigitalProjectAlertService::class)->notifyAttentionNeeded());

        Notification::assertSentToTimes($owner, DigitalProjectsDigestNotification::class, 1);
        $this->assertNotNull($stale->fresh()->alerted_at);
    }

    public function test_an_ownerless_project_alerts_every_admin_but_not_bystanders(): void
    {
        Notification::fake();
        $adminOne = $this->admin();
        $adminTwo = $this->admin();
        $bystander = User::factory()->create();
        DigitalProject::factory()->create(['created_at' => now()->subDays(20)]);

        app(DigitalProjectAlertService::class)->notifyAttentionNeeded();

        Notification::assertSentTo($adminOne, DigitalProjectsDigestNotification::class);
        Notification::assertSentTo($adminTwo, DigitalProjectsDigestNotification::class);
        Notification::assertNotSentTo($bystander, DigitalProjectsDigestNotification::class);
    }

    public function test_nobody_is_nagged_twice_in_a_week_and_an_action_is_flagged_once(): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $project = DigitalProject::factory()->create(['owner_id' => $owner->id, 'created_at' => now()->subDays(20)]);
        DigitalProjectLog::factory()->create(['digital_project_id' => $project->id, 'logged_on' => now()->subDays(1)]);
        $action = DigitalProjectAction::factory()->create(['digital_project_id' => $project->id, 'due_on' => now()->subDay()]);
        $service = app(DigitalProjectAlertService::class);

        $this->assertSame(1, $service->notifyAttentionNeeded());
        $this->assertSame(0, $service->notifyAttentionNeeded());
        $this->assertNotNull($action->fresh()->alerted_at);

        $project->logs()->delete();
        DigitalProject::query()->whereKey($project->id)->toBase()->update(['alerted_at' => now()->subDays(8)]);

        $this->assertSame(1, $service->notifyAttentionNeeded());
    }

    public function test_finished_paused_and_healthy_projects_never_alert(): void
    {
        Notification::fake();
        $old = now()->subDays(20);
        DigitalProject::factory()->create(['status' => DigitalProjectStatus::Paused, 'created_at' => $old, 'next_review_at' => now()->subWeek()]);
        DigitalProject::factory()->create(['status' => DigitalProjectStatus::Done, 'created_at' => $old, 'next_review_at' => now()->subWeek()]);
        $healthy = DigitalProject::factory()->create(['created_at' => $old, 'next_review_at' => now()->addWeek()]);
        DigitalProjectLog::factory()->create(['digital_project_id' => $healthy->id, 'logged_on' => today()]);
        DigitalProjectAction::factory()->create(['digital_project_id' => $healthy->id, 'due_on' => now()->addDay()]);
        DigitalProjectAction::factory()->create(['digital_project_id' => $healthy->id, 'due_on' => now()->subDay(), 'status' => DigitalProjectActionStatus::Done]);
        $this->admin();

        $this->assertSame(0, app(DigitalProjectAlertService::class)->notifyAttentionNeeded());

        Notification::assertNothingSent();
    }

    public function test_the_digest_is_scheduled_every_morning(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('digital-projects-attention-digest')->assertSuccessful();
    }
}
