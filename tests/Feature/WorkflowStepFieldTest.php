<?php

namespace Tests\Feature;

use App\Enums\ApproverType;
use App\Enums\FormFieldType;
use App\Enums\FormSubmissionStatus;
use App\Filament\Resources\FormSubmissions\Pages\CreateFormSubmission;
use App\Filament\Resources\FormSubmissions\Pages\ViewFormSubmission;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\FormTemplate;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Feature test for step-scoped form fields: a field like "نظر دفتر فنی"
 * that's filled in not by the original submitter, but by whoever acts
 * (approves/rejects) at a specific workflow step - see FormField::
 * workflowStep(), FormTemplate::generalFields(), and ViewFormSubmission's
 * approve/reject actions.
 */
class WorkflowStepFieldTest extends TestCase
{
    use RefreshDatabase;

    private function templateWithStepField(): array
    {
        $techOfficer = User::factory()->create();
        $manager = User::factory()->create();

        $workflow = WorkflowDefinition::factory()->create();
        $techStep = WorkflowStep::factory()->create([
            'workflow_definition_id' => $workflow->id,
            'step_order' => 1,
            'name' => 'تأیید دفتر فنی',
            'approver_type' => ApproverType::SpecificUser,
            'approver_user_id' => $techOfficer->id,
        ]);
        $managerStep = WorkflowStep::factory()->create([
            'workflow_definition_id' => $workflow->id,
            'step_order' => 2,
            'name' => 'تأیید مدیریت',
            'approver_type' => ApproverType::SpecificUser,
            'approver_user_id' => $manager->id,
        ]);

        $template = FormTemplate::factory()->create([
            'workflow_definition_id' => $workflow->id,
            'is_active' => true,
        ]);
        $subjectField = FormField::factory()->create([
            'form_template_id' => $template->id,
            'field_key' => 'subject',
            'field_type' => FormFieldType::Text,
            'is_required' => true,
        ]);
        $techNoteField = FormField::factory()->create([
            'form_template_id' => $template->id,
            'workflow_step_id' => $techStep->id,
            'field_key' => 'tech_note',
            'label' => 'نظر دفتر فنی',
            'field_type' => FormFieldType::Text,
        ]);

        return compact('template', 'subjectField', 'techNoteField', 'techStep', 'managerStep', 'techOfficer', 'manager');
    }

    public function test_general_fields_excludes_fields_scoped_to_a_workflow_step(): void
    {
        ['template' => $template, 'subjectField' => $subjectField] = $this->templateWithStepField();

        $generalKeys = $template->generalFields->pluck('field_key')->all();

        $this->assertSame([$subjectField->field_key], $generalKeys);
    }

    public function test_a_step_scoped_field_is_not_part_of_the_initial_submission_form(): void
    {
        ['template' => $template] = $this->templateWithStepField();

        $submitter = User::factory()->create();
        $this->actingAs($submitter);

        Livewire::test(CreateFormSubmission::class)
            ->set('data.form_template_id', $template->id)
            ->set('data.subject', 'درخواست تست')
            ->call('create')
            ->assertHasNoFormErrors();

        $submission = FormSubmission::sole();
        $this->assertSame('درخواست تست', $submission->fieldValue('subject'));
        $this->assertNull($submission->fieldValue('tech_note'));
    }

    public function test_approving_at_a_step_saves_that_steps_own_field_and_advances_the_workflow(): void
    {
        [
            'template' => $template,
            'techNoteField' => $techNoteField,
            'techOfficer' => $techOfficer,
            'managerStep' => $managerStep,
        ] = $this->templateWithStepField();

        $submitter = User::factory()->create();
        $submission = FormSubmission::factory()->create([
            'form_template_id' => $template->id,
            'submitted_by' => $submitter->id,
        ]);
        $submission->submit();

        Livewire::actingAs($techOfficer)
            ->test(ViewFormSubmission::class, ['record' => $submission->getRouteKey()])
            ->callAction('approve', data: ['comment' => null, 'tech_note' => 'مغایرتی مشاهده نشد']);

        $submission->refresh();
        $this->assertSame('مغایرتی مشاهده نشد', $submission->values()->where('form_field_id', $techNoteField->id)->first()?->value);
        $this->assertSame($managerStep->id, $submission->workflowInstance->current_step_id);
    }

    public function test_rejecting_at_a_step_also_saves_that_steps_own_field(): void
    {
        [
            'template' => $template,
            'techNoteField' => $techNoteField,
            'techOfficer' => $techOfficer,
        ] = $this->templateWithStepField();

        $submitter = User::factory()->create();
        $submission = FormSubmission::factory()->create([
            'form_template_id' => $template->id,
            'submitted_by' => $submitter->id,
        ]);
        $submission->submit();

        Livewire::actingAs($techOfficer)
            ->test(ViewFormSubmission::class, ['record' => $submission->getRouteKey()])
            ->callAction('reject', data: ['comment' => 'مدارک ناقص است', 'tech_note' => 'اسناد فنی کامل نیست']);

        $submission->refresh();
        $this->assertSame(FormSubmissionStatus::Rejected, $submission->status);
        $this->assertSame('اسناد فنی کامل نیست', $submission->values()->where('form_field_id', $techNoteField->id)->first()?->value);
    }

    public function test_a_required_step_scoped_field_blocks_approving_until_filled(): void
    {
        [
            'template' => $template,
            'techNoteField' => $techNoteField,
            'techOfficer' => $techOfficer,
        ] = $this->templateWithStepField();
        $techNoteField->update(['is_required' => true]);

        $submitter = User::factory()->create();
        $submission = FormSubmission::factory()->create([
            'form_template_id' => $template->id,
            'submitted_by' => $submitter->id,
        ]);
        $submission->submit();

        Livewire::actingAs($techOfficer)
            ->test(ViewFormSubmission::class, ['record' => $submission->getRouteKey()])
            ->callAction('approve', data: ['comment' => null, 'tech_note' => ''])
            ->assertHasActionErrors(['tech_note' => 'required']);

        $submission->refresh();
        $this->assertSame(FormSubmissionStatus::PendingApproval, $submission->status);
    }

    public function test_deleting_a_workflow_step_turns_its_fields_back_into_general_fields_instead_of_destroying_them(): void
    {
        ['template' => $template, 'techNoteField' => $techNoteField, 'techStep' => $techStep] = $this->templateWithStepField();

        $techStep->delete();

        $this->assertModelExists($techNoteField->fresh());
        $this->assertNull($techNoteField->fresh()->workflow_step_id);
        $this->assertContains('tech_note', $template->generalFields->pluck('field_key')->all());
    }
}
