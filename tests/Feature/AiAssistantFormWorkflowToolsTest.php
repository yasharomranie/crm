<?php

namespace Tests\Feature;

use App\Enums\AiMessageRole;
use App\Enums\ApproverType;
use App\Enums\FormFieldType;
use App\Enums\FormSubmissionStatus;
use App\Enums\WorkflowActionType;
use App\Filament\Pages\AiAssistant;
use App\Models\AiConversation;
use App\Models\AiProvider;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\FormTemplate;
use App\Models\User;
use App\Models\WorkflowAction;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Covers the ten form-builder/workflow analytics & editing tools added to
 * the AI assistant on top of design_workflow_draft/design_form_template_draft:
 * bottleneck analysis, approver performance, rejection reasons, period
 * comparison, submission search, decision-support summary, at-risk SLA,
 * workflow health check, and the two additive editing tools.
 */
class AiAssistantFormWorkflowToolsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Full access, including the resource "update" permissions the two
     * editing tools additionally require - see AiAssistantTest's identical
     * helper for why ai-assistant.access is required too.
     */
    private function actingAsFullyPermittedUser(): User
    {
        $this->artisan('access-control:sync');

        $role = Role::create(['name' => 'مدیر کامل تحلیل فرم‌ساز']);
        $role->givePermissionTo(Permission::whereIn('name', [
            'ai-assistant.access', 'ai-assistant.report', 'ai-assistant.act',
            'form-templates.create', 'form-templates.update',
            'workflow-definitions.create', 'workflow-definitions.update',
        ])->get());

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

    public function test_bottleneck_analysis_ranks_the_slowest_step_first(): void
    {
        AiProvider::factory()->active()->create();
        $workflow = WorkflowDefinition::factory()->create(['name' => 'گردش‌کار قرارداد']);
        $fastStep = WorkflowStep::factory()->create(['workflow_definition_id' => $workflow->id, 'name' => 'مرحلهٔ سریع']);
        $slowStep = WorkflowStep::factory()->create(['workflow_definition_id' => $workflow->id, 'name' => 'مرحلهٔ کند']);

        $fastInstance = WorkflowInstance::factory()->create([
            'workflow_definition_id' => $workflow->id,
            'started_at' => now()->subHours(3),
        ]);
        WorkflowAction::factory()->create([
            'workflow_instance_id' => $fastInstance->id,
            'workflow_step_id' => $fastStep->id,
            'acted_at' => now()->subHours(2), // 1 hour in step
        ]);

        $slowInstance = WorkflowInstance::factory()->create([
            'workflow_definition_id' => $workflow->id,
            'started_at' => now()->subDays(3),
        ]);
        WorkflowAction::factory()->create([
            'workflow_instance_id' => $slowInstance->id,
            'workflow_step_id' => $slowStep->id,
            'acted_at' => now()->subDays(1), // 48 hours in step
        ]);

        $this->fakeToolCallThenAnswer('get_workflow_bottleneck_analysis', []);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'کدوم مرحله بیشترین معطلی رو داره؟')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertStringContainsString('مرحلهٔ کند', $payload['bottlenecks'][0]['workflow_and_step']);
    }

    public function test_approver_performance_reports_rejection_rate_and_flags_a_named_approver(): void
    {
        AiProvider::factory()->active()->create();
        $approver = User::factory()->create(['name' => 'رضا کریمی']);
        $instance = WorkflowInstance::factory()->create(['started_at' => now()->subHours(5)]);

        WorkflowAction::factory()->create([
            'workflow_instance_id' => $instance->id,
            'actor_id' => $approver->id,
            'action' => WorkflowActionType::Reject,
            'acted_at' => now(),
        ]);

        $this->fakeToolCallThenAnswer('get_approver_performance', ['user_name' => 'رضا']);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'عملکرد رضا کریمی چطوره؟')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertCount(1, $payload['approvers']);
        $this->assertSame('رضا کریمی', $payload['approvers'][0]['user']);
        $this->assertEquals(100, $payload['approvers'][0]['rejection_rate_percent']);
    }

    public function test_rejection_reasons_returns_raw_comments_for_the_model_to_summarize(): void
    {
        AiProvider::factory()->active()->create();
        $template = FormTemplate::factory()->create(['title' => 'درخواست خرید']);
        $submission = FormSubmission::factory()->create(['form_template_id' => $template->id]);
        $instance = WorkflowInstance::factory()->create(['subject_id' => $submission->id, 'subject_type' => FormSubmission::class]);

        WorkflowAction::factory()->create([
            'workflow_instance_id' => $instance->id,
            'action' => WorkflowActionType::Reject,
            'comment' => 'مدارک ناقص بود',
        ]);

        $this->fakeToolCallThenAnswer('get_rejection_reasons', ['form_template_name' => 'درخواست خرید']);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'دلایل رد درخواست خرید چیه؟')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertSame(1, $payload['count']);
        $this->assertSame('مدارک ناقص بود', $payload['rejections'][0]['comment']);
    }

    public function test_compare_periods_tool_separates_this_month_from_last_month(): void
    {
        AiProvider::factory()->active()->create();
        $template = FormTemplate::factory()->create(['title' => 'مرخصی']);

        FormSubmission::factory()->create([
            'form_template_id' => $template->id,
            'status' => FormSubmissionStatus::Approved,
            'submitted_at' => now(),
        ]);
        FormSubmission::factory()->create([
            'form_template_id' => $template->id,
            'status' => FormSubmissionStatus::Rejected,
            'submitted_at' => now()->subMonthsNoOverflow(1),
        ]);

        $this->fakeToolCallThenAnswer('compare_form_submissions_between_periods', ['form_template_name' => 'مرخصی']);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'مرخصی این ماه نسبت به ماه قبل چطور بوده؟')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertSame(1, $payload['comparison']['این ماه']['approved']);
        $this->assertSame(1, $payload['comparison']['ماه قبل']['rejected']);
    }

    public function test_search_form_submissions_filters_by_a_numeric_field_threshold(): void
    {
        AiProvider::factory()->active()->create();
        $template = FormTemplate::factory()->create(['title' => 'درخواست خرید']);
        $amountField = FormField::factory()->create([
            'form_template_id' => $template->id,
            'field_key' => 'amount',
            'field_type' => FormFieldType::Number,
        ]);

        $bigSubmission = FormSubmission::factory()->create(['form_template_id' => $template->id]);
        $bigSubmission->values()->create(['form_field_id' => $amountField->id, 'value_number' => 800_000_000]);

        $smallSubmission = FormSubmission::factory()->create(['form_template_id' => $template->id]);
        $smallSubmission->values()->create(['form_field_id' => $amountField->id, 'value_number' => 50_000_000]);

        $this->fakeToolCallThenAnswer('search_form_submissions', [
            'form_template_name' => 'درخواست خرید',
            'field_key' => 'amount',
            'operator' => '>',
            'value' => '500000000',
        ]);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'درخواست‌های خرید بالای ۵۰۰ میلیون رو نشون بده')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertSame(1, $payload['matched_count']);
        $this->assertSame($bigSubmission->id, $payload['submissions'][0]['id']);
    }

    public function test_summarize_submission_for_decision_compares_against_past_decided_submissions(): void
    {
        AiProvider::factory()->active()->create();
        $template = FormTemplate::factory()->create(['title' => 'درخواست خرید']);
        $amountField = FormField::factory()->create([
            'form_template_id' => $template->id,
            'field_key' => 'amount',
            'label' => 'مبلغ',
            'field_type' => FormFieldType::Number,
        ]);

        FormSubmission::factory()->create(['form_template_id' => $template->id, 'status' => FormSubmissionStatus::Approved])
            ->values()->create(['form_field_id' => $amountField->id, 'value_number' => 100_000_000]);

        $pending = FormSubmission::factory()->create(['form_template_id' => $template->id, 'status' => FormSubmissionStatus::PendingApproval]);
        $pending->values()->create(['form_field_id' => $amountField->id, 'value_number' => 300_000_000]);

        $this->fakeToolCallThenAnswer('summarize_submission_for_decision', ['submission_id' => $pending->id]);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', "درخواست شمارهٔ {$pending->id} رو خلاصه کن")
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertEquals(100_000_000, $payload['numeric_field_comparisons'][0]['past_average']);
        $this->assertEquals(300_000_000, $payload['numeric_field_comparisons'][0]['this_value']);
    }

    public function test_at_risk_sla_submissions_flags_a_step_close_to_breach_but_not_yet_breached(): void
    {
        AiProvider::factory()->active()->create();
        // 1-hour SLA, 50 minutes elapsed - 83% through, not yet breached.
        $step = WorkflowStep::factory()->create(['sla_hours' => 1]);
        $instance = WorkflowInstance::factory()->create(['current_step_id' => $step->id]);
        DB::table('workflow_instances')->where('id', $instance->id)->update(['updated_at' => now()->subMinutes(50)]);

        $this->fakeToolCallThenAnswer('get_at_risk_sla_submissions', []);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'کدوم درخواست‌ها نزدیک به نقض SLA هستن؟')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertSame(1, $payload['at_risk_count']);
    }

    public function test_workflow_health_check_flags_a_role_step_with_no_members(): void
    {
        AiProvider::factory()->active()->create();
        $emptyRole = Role::create(['name' => 'نقش بدون عضو']);
        $workflow = WorkflowDefinition::factory()->create(['is_active' => true, 'subject_type' => FormSubmission::class]);
        WorkflowStep::factory()->create([
            'workflow_definition_id' => $workflow->id,
            'approver_type' => ApproverType::Role,
            'approver_role_id' => $emptyRole->id,
            'approver_user_id' => null,
        ]);

        $this->fakeToolCallThenAnswer('get_workflow_health_check', []);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'گردش‌کارها مشکلی دارن؟')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertSame(1, $payload['issues_count']);
        $this->assertStringContainsString('نقش بدون عضو', $payload['issues'][0]);
    }

    public function test_add_field_to_form_template_appends_without_touching_existing_fields(): void
    {
        AiProvider::factory()->active()->create();
        $template = FormTemplate::factory()->create(['title' => 'درخواست مرخصی']);
        $existingField = FormField::factory()->create(['form_template_id' => $template->id, 'field_key' => 'reason']);

        $this->fakeToolCallThenAnswer('add_field_to_form_template', [
            'form_template_name' => 'درخواست مرخصی',
            'field' => ['field_key' => 'contact_number', 'label' => 'شمارهٔ تماس', 'field_type' => 'text'],
        ]);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'یه فیلد شمارهٔ تماس به فرم مرخصی اضافه کن')
            ->call('send');

        $this->assertModelExists($existingField->fresh());
        $this->assertDatabaseHas('form_fields', ['form_template_id' => $template->id, 'field_key' => 'contact_number']);
        $this->assertSame(2, $template->fields()->count());
    }

    public function test_add_field_to_form_template_refuses_a_duplicate_field_key(): void
    {
        AiProvider::factory()->active()->create();
        $template = FormTemplate::factory()->create(['title' => 'درخواست مرخصی']);
        FormField::factory()->create(['form_template_id' => $template->id, 'field_key' => 'reason']);

        $this->fakeToolCallThenAnswer('add_field_to_form_template', [
            'form_template_name' => 'درخواست مرخصی',
            'field' => ['field_key' => 'reason', 'label' => 'دلیل تکراری', 'field_type' => 'text'],
        ]);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'یه فیلد دلیل دیگه به فرم مرخصی اضافه کن')
            ->call('send');

        $this->assertSame(1, $template->fields()->count());
    }

    public function test_update_workflow_step_changes_name_and_sla_but_the_tool_never_accepts_an_approver_change(): void
    {
        AiProvider::factory()->active()->create();
        $originalApprover = User::factory()->create();
        $workflow = WorkflowDefinition::factory()->create(['name' => 'گردش‌کار قرارداد']);
        $step = WorkflowStep::factory()->create([
            'workflow_definition_id' => $workflow->id,
            'name' => 'تأیید اولیه',
            'approver_type' => ApproverType::SpecificUser,
            'approver_user_id' => $originalApprover->id,
            'sla_hours' => 24,
        ]);

        $this->fakeToolCallThenAnswer('update_workflow_step', [
            'workflow_name' => 'گردش‌کار قرارداد',
            'step_name' => 'تأیید اولیه',
            'new_name' => 'تأیید نهایی',
            'sla_hours' => 48,
        ]);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'اسم مرحلهٔ تأیید اولیه رو به تأیید نهایی تغییر بده و مهلتش رو ۴۸ ساعت کن')
            ->call('send');

        $step->refresh();
        $this->assertSame('تأیید نهایی', $step->name);
        $this->assertSame(48, $step->sla_hours);
        $this->assertSame($originalApprover->id, $step->approver_user_id);
    }

    public function test_design_form_template_draft_refuses_a_near_duplicate_title_until_confirmed(): void
    {
        AiProvider::factory()->active()->create();
        FormTemplate::factory()->create(['title' => 'درخواست مرخصی سالانه']);

        $this->fakeToolCallThenAnswer('design_form_template_draft', [
            'title' => 'درخواست مرخصی سالانه ',
            'fields' => [['field_key' => 'reason', 'label' => 'دلیل', 'field_type' => 'text']],
        ]);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'یه فرم درخواست مرخصی سالانه بساز')
            ->call('send');

        $this->assertSame(1, FormTemplate::count());

        $payload = $this->toolResultPayload();
        $this->assertTrue($payload['needs_confirmation']);
    }

    /**
     * Even with "ai-assistant.act", the two editing tools must still
     * refuse a user who lacks the underlying resource "update" permission
     * - same principle already enforced for the design_* tools.
     */
    public function test_editing_tools_require_the_underlying_update_permission_even_with_ai_assistant_act(): void
    {
        AiProvider::factory()->active()->create();
        $template = FormTemplate::factory()->create(['title' => 'درخواست مرخصی']);

        $this->fakeToolCallThenAnswer('add_field_to_form_template', [
            'form_template_name' => 'درخواست مرخصی',
            'field' => ['field_key' => 'extra', 'label' => 'فیلد جدید', 'field_type' => 'text'],
        ]);

        $this->artisan('access-control:sync');
        $role = Role::create(['name' => 'اقدام‌گر بدون ویرایش فرم']);
        $role->givePermissionTo(Permission::whereIn('name', ['ai-assistant.access', 'ai-assistant.act'])->get());
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        Livewire::test(AiAssistant::class)
            ->set('body', 'یه فیلد جدید به فرم مرخصی اضافه کن')
            ->call('send');

        $this->assertSame(0, $template->fields()->count());
    }
}
