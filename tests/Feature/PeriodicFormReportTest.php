<?php

namespace Tests\Feature;

use App\Enums\FormSubmissionStatus;
use App\Filament\Pages\PeriodicFormReport;
use App\Filament\Resources\FormSubmissions\FormSubmissionResource;
use App\Models\FormSubmission;
use App\Models\FormTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Morilog\Jalali\Jalalian;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PeriodicFormReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('access-control:sync');
    }

    public function test_a_role_without_the_permission_is_denied_access(): void
    {
        $role = Role::create(['name' => 'بدون دسترسی گزارش']);

        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        $this->assertFalse(PeriodicFormReport::canAccess());
    }

    public function test_a_user_with_no_role_keeps_unrestricted_access(): void
    {
        $this->actingAs(User::factory()->create());

        $this->assertTrue(PeriodicFormReport::canAccess());
    }

    public function test_the_monthly_calendar_marks_the_day_of_a_submission_and_leaves_other_days_empty(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $template = FormTemplate::factory()->create();

        // A day comfortably in the middle of the current Jalali month, so
        // shifting a couple of days never crosses a month boundary.
        $submissionDay = Jalalian::now()->getFirstDayOfMonth()->addDays(9)->toCarbon();
        $emptyDay = $submissionDay->copy()->addDay();

        $submission = FormSubmission::factory()->create([
            'form_template_id' => $template->id,
            'submitted_by' => $user->id,
            'status' => FormSubmissionStatus::Approved,
            'submitted_at' => $submissionDay,
        ]);

        $component = Livewire::test(PeriodicFormReport::class)
            ->set('formTemplateId', $template->id)
            ->set('periodType', 'monthly')
            ->set('anchorDate', $submissionDay->toDateString());

        $cells = collect($component->instance()->getWeeks())->flatten(1);

        $filledCell = $cells->firstWhere(fn (array $cell) => $cell['carbon']->isSameDay($submissionDay));
        $emptyCell = $cells->firstWhere(fn (array $cell) => $cell['carbon']->isSameDay($emptyDay));

        $this->assertNotNull($filledCell['submission']);
        $this->assertTrue($filledCell['submission']->is($submission));
        $this->assertNull($emptyCell['submission']);
    }

    public function test_the_weekly_period_spans_exactly_seven_days(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $template = FormTemplate::factory()->create();

        $component = Livewire::test(PeriodicFormReport::class)
            ->set('formTemplateId', $template->id)
            ->set('periodType', 'weekly');

        $cells = collect($component->instance()->getWeeks())->flatten(1);

        $this->assertSame(7, $cells->where('inPeriod', true)->count());
    }

    public function test_a_user_without_view_any_cannot_use_the_filter_to_see_someone_elses_submissions(): void
    {
        $role = Role::create(['name' => 'کارمند بدون دسترسی گسترده']);
        $role->givePermissionTo(Permission::where('name', 'periodic-form-report.access')->sole());

        $viewer = User::factory()->create();
        $viewer->assignRole($role);
        $this->actingAs($viewer);

        $someoneElse = User::factory()->create();
        $template = FormTemplate::factory()->create();

        $today = now();

        FormSubmission::factory()->create([
            'form_template_id' => $template->id,
            'submitted_by' => $someoneElse->id,
            'status' => FormSubmissionStatus::Approved,
            'submitted_at' => $today,
        ]);

        $component = Livewire::test(PeriodicFormReport::class)
            ->set('formTemplateId', $template->id)
            // A tampered/forced value - the viewer has no UI control that
            // could set this since canSelectOtherUsers() is false for them,
            // but the property is still a public Livewire property.
            ->set('userId', $someoneElse->id)
            ->set('anchorDate', $today->toDateString());

        $this->assertFalse($component->instance()->canSelectOtherUsers());

        $cells = collect($component->instance()->getWeeks())->flatten(1);
        $todayCell = $cells->firstWhere(fn (array $cell) => $cell['carbon']->isSameDay($today));

        $this->assertNull($todayCell['submission']);
    }

    public function test_a_user_with_view_any_can_see_another_users_submissions(): void
    {
        $role = Role::create(['name' => 'ناظر فرم‌ها']);
        $role->givePermissionTo(Permission::whereIn('name', [
            'periodic-form-report.access',
            'form-submissions.view_any',
        ])->get());

        $manager = User::factory()->create();
        $manager->assignRole($role);
        $this->actingAs($manager);

        $employee = User::factory()->create();
        $template = FormTemplate::factory()->create();

        $today = now();

        $submission = FormSubmission::factory()->create([
            'form_template_id' => $template->id,
            'submitted_by' => $employee->id,
            'status' => FormSubmissionStatus::PendingApproval,
            'submitted_at' => $today,
        ]);

        $component = Livewire::test(PeriodicFormReport::class)
            ->set('formTemplateId', $template->id)
            ->set('userId', $employee->id)
            ->set('anchorDate', $today->toDateString());

        $this->assertTrue($component->instance()->canSelectOtherUsers());

        $cells = collect($component->instance()->getWeeks())->flatten(1);
        $todayCell = $cells->firstWhere(fn (array $cell) => $cell['carbon']->isSameDay($today));

        $this->assertNotNull($todayCell['submission']);
        $this->assertTrue($todayCell['submission']->is($submission));
    }

    public function test_the_submission_url_points_to_the_form_submission_view_page(): void
    {
        $this->actingAs(User::factory()->create());

        $submission = FormSubmission::factory()->create();

        $component = Livewire::test(PeriodicFormReport::class);

        $this->assertSame(
            FormSubmissionResource::getUrl('view', ['record' => $submission]),
            $component->instance()->getSubmissionUrl($submission)
        );
    }
}
