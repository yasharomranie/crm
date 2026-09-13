<?php

namespace Tests\Feature;

use App\Enums\ApproverType;
use App\Enums\FormFieldType;
use App\Enums\FormSubmissionStatus;
use App\Enums\WorkflowActionType;
use App\Enums\WorkflowInstanceStatus;
use App\Exceptions\WorkflowException;
use App\Models\Department;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\FormSubmissionValue;
use App\Models\FormTemplate;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkflowEngineTest extends TestCase
{
    use RefreshDatabase;

    private function makeSubmissionWithAmount(WorkflowDefinition $workflow, Department $department, User $requester, float $amount): FormSubmission
    {
        $template = FormTemplate::factory()->create([
            'workflow_definition_id' => $workflow->id,
            'department_id' => $department->id,
        ]);
        $amountField = FormField::factory()->create([
            'form_template_id' => $template->id,
            'field_key' => 'amount',
            'field_type' => FormFieldType::Number,
        ]);

        $submission = FormSubmission::factory()->create([
            'form_template_id' => $template->id,
            'submitted_by' => $requester->id,
            'department_id' => $department->id,
            'status' => FormSubmissionStatus::Draft,
        ]);
        FormSubmissionValue::factory()->create([
            'form_submission_id' => $submission->id,
            'form_field_id' => $amountField->id,
            'value_number' => $amount,
        ]);

        return $submission->load('values.formField');
    }

    public function test_a_step_without_condition_rules_always_applies(): void
    {
        $step = WorkflowStep::factory()->make(['condition_rules' => null]);
        $submission = FormSubmission::factory()->make();

        $this->assertTrue($step->appliesTo($submission));
    }

    public function test_a_step_is_skipped_when_its_condition_is_not_met(): void
    {
        $dept = Department::factory()->create();
        $manager = User::factory()->create();
        $dept->update(['manager_id' => $manager->id]);
        $requester = User::factory()->create(['department_id' => $dept->id]);
        $financeApprover = User::factory()->create();

        $workflow = WorkflowDefinition::factory()->create();
        WorkflowStep::factory()->create([
            'workflow_definition_id' => $workflow->id, 'step_order' => 1,
            'approver_type' => ApproverType::DepartmentManager,
        ]);
        WorkflowStep::factory()->create([
            'workflow_definition_id' => $workflow->id, 'step_order' => 2,
            'approver_type' => ApproverType::SpecificUser, 'approver_user_id' => $financeApprover->id,
            'condition_rules' => [['field' => 'amount', 'operator' => '>', 'value' => 1_000_000]],
        ]);

        $submission = $this->makeSubmissionWithAmount($workflow, $dept, $requester, 500_000);
        $submission->submit();

        $submission->workflowInstance->act($manager, WorkflowActionType::Approve);
        $submission->refresh();

        $this->assertSame(WorkflowInstanceStatus::Approved, $submission->workflowInstance->status);
        $this->assertSame(FormSubmissionStatus::Approved, $submission->status);
    }

    public function test_a_step_is_reached_when_its_condition_is_met(): void
    {
        $dept = Department::factory()->create();
        $manager = User::factory()->create();
        $dept->update(['manager_id' => $manager->id]);
        $requester = User::factory()->create(['department_id' => $dept->id]);
        $financeApprover = User::factory()->create();

        $workflow = WorkflowDefinition::factory()->create();
        WorkflowStep::factory()->create([
            'workflow_definition_id' => $workflow->id, 'step_order' => 1,
            'approver_type' => ApproverType::DepartmentManager,
        ]);
        WorkflowStep::factory()->create([
            'workflow_definition_id' => $workflow->id, 'step_order' => 2,
            'approver_type' => ApproverType::SpecificUser, 'approver_user_id' => $financeApprover->id,
            'condition_rules' => [['field' => 'amount', 'operator' => '>', 'value' => 1_000_000]],
        ]);

        $submission = $this->makeSubmissionWithAmount($workflow, $dept, $requester, 2_000_000);
        $submission->submit();

        $instance = $submission->workflowInstance;
        $instance->act($manager, WorkflowActionType::Approve);
        $instance->refresh();

        $this->assertSame(WorkflowInstanceStatus::InProgress, $instance->status);
        $this->assertTrue($instance->currentApprovers()->contains(fn (User $u) => $u->is($financeApprover)));

        $instance->act($financeApprover, WorkflowActionType::Approve);
        $submission->refresh();
        $this->assertSame(FormSubmissionStatus::Approved, $submission->status);
    }

    public function test_submitting_a_template_without_a_workflow_approves_immediately(): void
    {
        $template = FormTemplate::factory()->create(['workflow_definition_id' => null]);
        $submission = FormSubmission::factory()->create([
            'form_template_id' => $template->id,
            'status' => FormSubmissionStatus::Draft,
        ]);

        $submission->submit();

        $this->assertSame(FormSubmissionStatus::Approved, $submission->fresh()->status);
        $this->assertNull($submission->workflowInstance);
    }

    public function test_rejecting_ends_the_workflow_as_rejected(): void
    {
        $approver = User::factory()->create();
        $workflow = WorkflowDefinition::factory()->create();
        WorkflowStep::factory()->create([
            'workflow_definition_id' => $workflow->id, 'step_order' => 1,
            'approver_type' => ApproverType::SpecificUser, 'approver_user_id' => $approver->id,
        ]);
        $template = FormTemplate::factory()->create(['workflow_definition_id' => $workflow->id]);
        $submission = FormSubmission::factory()->create([
            'form_template_id' => $template->id,
            'status' => FormSubmissionStatus::Draft,
        ]);
        $submission->submit();

        $submission->workflowInstance->act($approver, WorkflowActionType::Reject, 'مدارک ناقص است');
        $submission->refresh();

        $this->assertSame(FormSubmissionStatus::Rejected, $submission->status);
        $this->assertSame(WorkflowInstanceStatus::Rejected, $submission->workflowInstance->status);
    }

    public function test_a_user_who_is_not_the_resolved_approver_cannot_act(): void
    {
        $approver = User::factory()->create();
        $stranger = User::factory()->create();
        $workflow = WorkflowDefinition::factory()->create();
        WorkflowStep::factory()->create([
            'workflow_definition_id' => $workflow->id, 'step_order' => 1,
            'approver_type' => ApproverType::SpecificUser, 'approver_user_id' => $approver->id,
        ]);
        $template = FormTemplate::factory()->create(['workflow_definition_id' => $workflow->id]);
        $submission = FormSubmission::factory()->create([
            'form_template_id' => $template->id,
            'status' => FormSubmissionStatus::Draft,
        ]);
        $submission->submit();

        $this->expectException(WorkflowException::class);
        $submission->workflowInstance->act($stranger, WorkflowActionType::Approve);
    }

    public function test_an_admin_can_act_even_without_being_the_resolved_approver(): void
    {
        $approver = User::factory()->create();
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        $workflow = WorkflowDefinition::factory()->create();
        WorkflowStep::factory()->create([
            'workflow_definition_id' => $workflow->id, 'step_order' => 1,
            'approver_type' => ApproverType::SpecificUser, 'approver_user_id' => $approver->id,
        ]);
        $template = FormTemplate::factory()->create(['workflow_definition_id' => $workflow->id]);
        $submission = FormSubmission::factory()->create([
            'form_template_id' => $template->id,
            'status' => FormSubmissionStatus::Draft,
        ]);
        $submission->submit();

        $submission->workflowInstance->act($admin, WorkflowActionType::Approve);

        $this->assertSame(FormSubmissionStatus::Approved, $submission->fresh()->status);
    }

    public function test_only_the_submitter_can_cancel_a_pending_request(): void
    {
        $requester = User::factory()->create();
        $stranger = User::factory()->create();
        $approver = User::factory()->create();

        $workflow = WorkflowDefinition::factory()->create();
        WorkflowStep::factory()->create([
            'workflow_definition_id' => $workflow->id, 'step_order' => 1,
            'approver_type' => ApproverType::SpecificUser, 'approver_user_id' => $approver->id,
        ]);
        $template = FormTemplate::factory()->create(['workflow_definition_id' => $workflow->id]);
        $submission = FormSubmission::factory()->create([
            'form_template_id' => $template->id,
            'submitted_by' => $requester->id,
            'status' => FormSubmissionStatus::Draft,
        ]);
        $submission->submit();

        $this->expectException(WorkflowException::class);
        $submission->workflowInstance->act($stranger, WorkflowActionType::Cancel);
    }

    public function test_the_submitter_can_cancel_their_own_pending_request(): void
    {
        $requester = User::factory()->create();
        $approver = User::factory()->create();

        $workflow = WorkflowDefinition::factory()->create();
        WorkflowStep::factory()->create([
            'workflow_definition_id' => $workflow->id, 'step_order' => 1,
            'approver_type' => ApproverType::SpecificUser, 'approver_user_id' => $approver->id,
        ]);
        $template = FormTemplate::factory()->create(['workflow_definition_id' => $workflow->id]);
        $submission = FormSubmission::factory()->create([
            'form_template_id' => $template->id,
            'submitted_by' => $requester->id,
            'status' => FormSubmissionStatus::Draft,
        ]);
        $submission->submit();

        $submission->workflowInstance->act($requester, WorkflowActionType::Cancel);
        $submission->refresh();

        $this->assertSame(FormSubmissionStatus::Cancelled, $submission->status);
    }

    public function test_referring_in_parallel_requires_a_comment(): void
    {
        $approver = User::factory()->create();
        $workflow = WorkflowDefinition::factory()->create();
        WorkflowStep::factory()->create([
            'workflow_definition_id' => $workflow->id, 'step_order' => 1,
            'approver_type' => ApproverType::SpecificUser, 'approver_user_id' => $approver->id,
        ]);
        $template = FormTemplate::factory()->create(['workflow_definition_id' => $workflow->id]);
        $submission = FormSubmission::factory()->create([
            'form_template_id' => $template->id,
            'status' => FormSubmissionStatus::Draft,
        ]);
        $submission->submit();

        $this->expectException(WorkflowException::class);
        $submission->workflowInstance->act($approver, WorkflowActionType::ReferParallel);
    }

    public function test_acting_on_an_already_finished_instance_fails(): void
    {
        $approver = User::factory()->create();
        $workflow = WorkflowDefinition::factory()->create();
        WorkflowStep::factory()->create([
            'workflow_definition_id' => $workflow->id, 'step_order' => 1,
            'approver_type' => ApproverType::SpecificUser, 'approver_user_id' => $approver->id,
        ]);
        $template = FormTemplate::factory()->create(['workflow_definition_id' => $workflow->id]);
        $submission = FormSubmission::factory()->create([
            'form_template_id' => $template->id,
            'status' => FormSubmissionStatus::Draft,
        ]);
        $submission->submit();
        $submission->workflowInstance->act($approver, WorkflowActionType::Approve);

        $this->expectException(WorkflowException::class);
        $submission->workflowInstance->act($approver, WorkflowActionType::Comment, 'دیرشده');
    }

    public function test_resolve_approvers_for_role_type(): void
    {
        $role = Role::create(['name' => 'حسابدار']);
        $accountant = User::factory()->create();
        $accountant->assignRole($role);

        $step = WorkflowStep::factory()->create(['approver_type' => ApproverType::Role, 'approver_role_id' => $role->id]);
        $submission = FormSubmission::factory()->create();

        $this->assertTrue($step->resolveApprovers($submission)->contains(fn (User $u) => $u->is($accountant)));
    }

    public function test_scope_visible_to_includes_submitter_and_approver_but_not_a_stranger(): void
    {
        $role = Role::create(['name' => 'کارمند']);
        $dept = Department::factory()->create();
        $manager = User::factory()->create();
        $dept->update(['manager_id' => $manager->id]);
        $requester = User::factory()->create(['department_id' => $dept->id]);
        $stranger = User::factory()->create();
        foreach ([$manager, $requester, $stranger] as $user) {
            $user->assignRole($role);
        }

        $workflow = WorkflowDefinition::factory()->create();
        WorkflowStep::factory()->create(['workflow_definition_id' => $workflow->id, 'step_order' => 1, 'approver_type' => ApproverType::DepartmentManager]);
        $template = FormTemplate::factory()->create(['workflow_definition_id' => $workflow->id, 'department_id' => $dept->id]);
        $submission = FormSubmission::factory()->create([
            'form_template_id' => $template->id,
            'submitted_by' => $requester->id,
            'department_id' => $dept->id,
            'status' => FormSubmissionStatus::Draft,
        ]);
        $submission->submit();

        $this->assertTrue($submission->isVisibleTo($requester));
        $this->assertTrue($submission->isVisibleTo($manager));
        $this->assertFalse($submission->isVisibleTo($stranger));

        $this->assertTrue(FormSubmission::query()->awaitingActionFrom($manager)->whereKey($submission->id)->exists());
        $this->assertFalse(FormSubmission::query()->awaitingActionFrom($requester)->whereKey($submission->id)->exists());
    }
}
