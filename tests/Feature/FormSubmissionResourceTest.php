<?php

namespace Tests\Feature;

use App\Enums\ApproverType;
use App\Enums\FormFieldType;
use App\Enums\FormSubmissionStatus;
use App\Filament\Resources\FormSubmissions\Pages\CreateFormSubmission;
use App\Filament\Resources\FormSubmissions\Pages\ListFormSubmissions;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\FormTemplate;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FormSubmissionResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitting_the_dynamic_form_creates_values_and_starts_the_workflow(): void
    {
        $approver = User::factory()->create();
        $workflow = WorkflowDefinition::factory()->create();
        WorkflowStep::factory()->create([
            'workflow_definition_id' => $workflow->id, 'step_order' => 1,
            'approver_type' => ApproverType::SpecificUser, 'approver_user_id' => $approver->id,
        ]);
        $template = FormTemplate::factory()->create(['workflow_definition_id' => $workflow->id, 'is_active' => true]);
        $titleField = FormField::factory()->create([
            'form_template_id' => $template->id,
            'field_key' => 'subject',
            'field_type' => FormFieldType::Text,
            'is_required' => true,
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(CreateFormSubmission::class)
            ->set('data.form_template_id', $template->id)
            ->set('data.subject', 'درخواست خرید تجهیزات')
            ->call('create')
            ->assertHasNoFormErrors();

        $submission = FormSubmission::sole();
        $this->assertSame($template->id, $submission->form_template_id);
        $this->assertSame($user->id, $submission->submitted_by);
        $this->assertSame(FormSubmissionStatus::PendingApproval, $submission->status);
        $this->assertSame('درخواست خرید تجهیزات', $submission->values()->where('form_field_id', $titleField->id)->first()->value);
        $this->assertNotNull($submission->workflowInstance);
        $this->assertTrue($submission->workflowInstance->canBeActedOnBy($approver));
    }

    public function test_a_template_with_no_workflow_is_approved_immediately_on_submit(): void
    {
        $template = FormTemplate::factory()->create(['workflow_definition_id' => null, 'is_active' => true]);

        $this->actingAs(User::factory()->create());

        Livewire::test(CreateFormSubmission::class)
            ->set('data.form_template_id', $template->id)
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(FormSubmissionStatus::Approved, FormSubmission::sole()->status);
    }

    public function test_the_inbox_tab_only_lists_submissions_awaiting_the_current_user(): void
    {
        $approver = User::factory()->create();
        $someoneElse = User::factory()->create();

        $workflow = WorkflowDefinition::factory()->create();
        WorkflowStep::factory()->create([
            'workflow_definition_id' => $workflow->id, 'step_order' => 1,
            'approver_type' => ApproverType::SpecificUser, 'approver_user_id' => $approver->id,
        ]);
        $template = FormTemplate::factory()->create(['workflow_definition_id' => $workflow->id]);

        $mine = FormSubmission::factory()->create(['form_template_id' => $template->id, 'status' => FormSubmissionStatus::Draft]);
        $mine->submit();

        $otherWorkflow = WorkflowDefinition::factory()->create();
        WorkflowStep::factory()->create([
            'workflow_definition_id' => $otherWorkflow->id, 'step_order' => 1,
            'approver_type' => ApproverType::SpecificUser, 'approver_user_id' => $someoneElse->id,
        ]);
        $otherTemplate = FormTemplate::factory()->create(['workflow_definition_id' => $otherWorkflow->id]);
        $notMine = FormSubmission::factory()->create(['form_template_id' => $otherTemplate->id, 'status' => FormSubmissionStatus::Draft]);
        $notMine->submit();

        $this->actingAs($approver);

        Livewire::test(ListFormSubmissions::class)
            ->set('activeTab', 'inbox')
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$notMine]);
    }

    public function test_print_route_is_denied_to_someone_with_no_relation_to_the_submission(): void
    {
        $submission = FormSubmission::factory()->create();

        $stranger = User::factory()->create();
        $stranger->assignRole(Role::create(['name' => 'بدون دسترسی فرم']));

        $this->actingAs($stranger)
            ->get(route('form-submissions.print', $submission))
            ->assertForbidden();
    }

    public function test_print_route_is_allowed_to_the_submitter(): void
    {
        $submitter = User::factory()->create();
        $submission = FormSubmission::factory()->create(['submitted_by' => $submitter->id]);

        $this->actingAs($submitter)
            ->get(route('form-submissions.print', $submission))
            ->assertOk()
            ->assertSee($submission->reference_code);
    }
}
