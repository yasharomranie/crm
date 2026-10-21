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
use App\Models\FormSubmissionValue;
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
        ])->orWhere('name', 'like', '%.view_any')->orWhere('name', 'tasks.create')->get());

        $user = User::factory()->create(['name' => 'مدیر آزمایشی']);
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

    /**
     * get_form_template_fields lets the model discover a form's field
     * labels/keys/types (and a Table field's own column definitions)
     * before calling search_form_submissions, instead of having to guess
     * a technical field_key from the user's plain-language wording.
     */
    public function test_get_form_template_fields_lists_labels_keys_types_and_table_columns(): void
    {
        AiProvider::factory()->active()->create();
        $template = FormTemplate::factory()->create(['title' => 'صورتجلسه کمیسیون هفتگی']);
        FormField::factory()->create([
            'form_template_id' => $template->id,
            'field_key' => 'department_name',
            'label' => 'نام دپارتمان',
            'field_type' => FormFieldType::Text,
            'sort_order' => 1,
        ]);
        FormField::factory()->table()->create([
            'form_template_id' => $template->id,
            'field_key' => 'tasks',
            'label' => 'جدول',
            'sort_order' => 2,
        ]);

        $this->fakeToolCallThenAnswer('get_form_template_fields', ['form_template_name' => 'صورتجلسه کمیسیون هفتگی']);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'فیلدهای فرم صورتجلسه کمیسیون هفتگی رو بگو')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertSame('صورتجلسه کمیسیون هفتگی', $payload['form']);
        $this->assertSame('department_name', $payload['fields'][0]['field_key']);
        $this->assertNull($payload['fields'][0]['table_columns']);
        $this->assertSame('tasks', $payload['fields'][1]['field_key']);
        $this->assertSame('unit_name', $payload['fields'][1]['table_columns'][0]['key']);
    }

    /**
     * search_form_submissions's include_answers option is what makes
     * "analyze every digital-marketing department's weekly meeting minutes"
     * possible in one round trip - every matched submission's full answers
     * come back together, table-type fields included as their complete row
     * array, instead of one summarize_submission_for_decision call per id.
     */
    public function test_search_form_submissions_with_include_answers_returns_full_field_data_including_table_rows(): void
    {
        AiProvider::factory()->active()->create();
        $template = FormTemplate::factory()->create(['title' => 'صورتجلسه کمیسیون هفتگی']);
        $departmentField = FormField::factory()->create([
            'form_template_id' => $template->id,
            'field_key' => 'department_name',
            'label' => 'نام دپارتمان',
            'field_type' => FormFieldType::Text,
        ]);
        $tableField = FormField::factory()->table()->create([
            'form_template_id' => $template->id,
            'field_key' => 'tasks',
            'label' => 'جدول',
        ]);

        $submission = FormSubmission::factory()->create(['form_template_id' => $template->id]);
        $submission->values()->create(['form_field_id' => $departmentField->id, 'value_text' => 'دیجیتال مارکتینگ']);
        $submission->values()->create([
            'form_field_id' => $tableField->id,
            'value_json' => [
                ['unit_name' => 'طراحی وب سایت', 'amount' => 100],
                ['unit_name' => 'تولید محتوا', 'amount' => 40],
            ],
        ]);

        FormSubmission::factory()->create(['form_template_id' => $template->id])
            ->values()->create(['form_field_id' => $departmentField->id, 'value_text' => 'مالی']);

        $this->fakeToolCallThenAnswer('search_form_submissions', [
            'form_template_name' => 'صورتجلسه کمیسیون هفتگی',
            'field_key' => 'department_name',
            'operator' => 'contains',
            'value' => 'دیجیتال مارکتینگ',
            'include_answers' => true,
        ]);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'صورتجلسه‌های واحد دیجیتال مارکتینگ رو آنالیز کن')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertSame(1, $payload['matched_count']);
        $answers = collect($payload['submissions'][0]['answers']);
        $this->assertSame('دیجیتال مارکتینگ', $answers->firstWhere('field', 'نام دپارتمان')['value']);
        $tableAnswer = $answers->firstWhere('field', 'جدول')['value'];
        $this->assertCount(2, $tableAnswer);
        $this->assertSame('طراحی وب سایت', $tableAnswer[0]['unit_name']);
        $this->assertSame(100, $tableAnswer[0]['amount']);
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

    /**
     * The regression this tool exists for: get_form_submissions_summary's
     * sla_breached_count alone never named who to follow up with - this
     * must resolve and return the actual responsible approver's name.
     */
    public function test_breached_sla_submissions_names_the_responsible_approver_and_hours_overdue(): void
    {
        AiProvider::factory()->active()->create();
        $approver = User::factory()->create(['name' => 'نرگس احمدی']);
        $template = FormTemplate::factory()->create(['title' => 'درخواست مرخصی']);
        $step = WorkflowStep::factory()->create([
            'sla_hours' => 1,
            'approver_type' => ApproverType::SpecificUser,
            'approver_user_id' => $approver->id,
        ]);
        $submission = FormSubmission::factory()->create([
            'form_template_id' => $template->id,
            'status' => FormSubmissionStatus::PendingApproval,
        ]);
        $instance = WorkflowInstance::factory()->create([
            'subject_id' => $submission->id,
            'current_step_id' => $step->id,
        ]);
        // 1-hour SLA, 3 hours elapsed since the instance last moved to this
        // step - clearly already breached, not just at-risk.
        DB::table('workflow_instances')->where('id', $instance->id)->update(['updated_at' => now()->subHours(3)]);

        $this->fakeToolCallThenAnswer('get_breached_sla_submissions', []);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'کدوم فرم‌ها از مهلت تاییدشون گذشته و کی باید پیگیریش کنم؟')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertSame(1, $payload['breached_count']);
        $this->assertSame('درخواست مرخصی', $payload['items'][0]['form']);
        $this->assertSame(['نرگس احمدی'], $payload['items'][0]['responsible_approvers']);
        $this->assertEquals(2, $payload['items'][0]['overdue_hours']);
    }

    public function test_breached_sla_submissions_can_be_scoped_to_one_approver_and_excludes_others(): void
    {
        AiProvider::factory()->active()->create();
        $followedUp = User::factory()->create(['name' => 'رضا کریمی']);
        $someoneElse = User::factory()->create(['name' => 'سارا محمدی']);

        $matchingStep = WorkflowStep::factory()->create(['sla_hours' => 1, 'approver_user_id' => $followedUp->id]);
        $matchingInstance = WorkflowInstance::factory()->create(['current_step_id' => $matchingStep->id]);
        DB::table('workflow_instances')->where('id', $matchingInstance->id)->update(['updated_at' => now()->subHours(5)]);

        $otherStep = WorkflowStep::factory()->create(['sla_hours' => 1, 'approver_user_id' => $someoneElse->id]);
        $otherInstance = WorkflowInstance::factory()->create(['current_step_id' => $otherStep->id]);
        DB::table('workflow_instances')->where('id', $otherInstance->id)->update(['updated_at' => now()->subHours(5)]);

        $this->fakeToolCallThenAnswer('get_breached_sla_submissions', ['approver_name' => 'رضا']);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'رضا کریمی چه فرمی رو هنوز تایید نکرده؟')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertSame(1, $payload['breached_count']);
        $this->assertSame(['رضا کریمی'], $payload['items'][0]['responsible_approvers']);
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
            ->call('send')
            ->call('confirmPendingAction');

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
            ->call('send')
            ->call('confirmPendingAction');

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
            ->call('send')
            ->call('confirmPendingAction');

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
            ->call('send')
            ->call('confirmPendingAction');

        $this->assertSame(0, $template->fields()->count());
    }

    public function test_design_form_template_draft_stores_the_contract_text_with_placeholders_and_it_merges_into_a_submission(): void
    {
        AiProvider::factory()->active()->create();

        $this->fakeToolCallThenAnswer('design_form_template_draft', [
            'title' => 'قرارداد پیمانکاری',
            'fields' => [
                ['field_key' => 'contractor_name', 'label' => 'نام پیمانکار', 'field_type' => 'text'],
                ['field_key' => 'national_id', 'label' => 'کد ملی', 'field_type' => 'text'],
                ['field_key' => 'unused_note', 'label' => 'توضیح', 'field_type' => 'textarea'],
            ],
            'document_template' => "این قرارداد با آقای {{contractor_name}} به کد ملی {{ national_id }} منعقد می‌شود.\n\nپیمانکار متعهد است کار را به‌موقع تحویل دهد.",
        ]);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'این قرارداد رو تبدیل به فرم کن')
            ->call('send');

        $template = FormTemplate::firstOrFail();

        $this->assertFalse($template->is_active);
        $this->assertSame(
            '<p>این قرارداد با آقای {{contractor_name}} به کد ملی {{national_id}} منعقد می‌شود.</p><p>پیمانکار متعهد است کار را به‌موقع تحویل دهد.</p>',
            $template->document_template,
        );

        $payload = $this->toolResultPayload();

        $this->assertTrue($payload['document_template_saved']);
        $this->assertSame(['unused_note'], $payload['fields_not_used_in_document']);

        $submission = FormSubmission::factory()->create(['form_template_id' => $template->id]);
        $nameField = $template->fields()->where('field_key', 'contractor_name')->firstOrFail();
        FormSubmissionValue::factory()->create(['form_submission_id' => $submission->id, 'form_field_id' => $nameField->id, 'value_text' => 'یاشار عمرانی']);

        $this->assertStringContainsString('با آقای یاشار عمرانی به کد ملی', $submission->fresh()->mergedDocumentBody());
    }

    public function test_design_form_template_draft_refuses_a_placeholder_that_matches_no_field_and_creates_nothing(): void
    {
        AiProvider::factory()->active()->create();

        $this->fakeToolCallThenAnswer('design_form_template_draft', [
            'title' => 'قرارداد',
            'fields' => [['field_key' => 'contractor_name', 'label' => 'نام', 'field_type' => 'text']],
            'document_template' => 'نام {{contractor_name}} و مبلغ {{amount}}',
        ]);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'فرم بساز')
            ->call('send');

        $this->assertSame(0, FormTemplate::count());
        $this->assertStringContainsString('amount', $this->toolResultPayload()['error']);
    }

    public function test_the_document_text_is_escaped_so_the_model_cannot_smuggle_markup_into_a_printed_contract(): void
    {
        AiProvider::factory()->active()->create();

        $this->fakeToolCallThenAnswer('design_form_template_draft', [
            'title' => 'قرارداد',
            'fields' => [['field_key' => 'contractor_name', 'label' => 'نام', 'field_type' => 'text']],
            'document_template' => '<script>alert(1)</script> نام {{contractor_name}}',
        ]);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'فرم بساز')
            ->call('send');

        $stored = FormTemplate::firstOrFail()->document_template;

        $this->assertStringNotContainsString('<script>', $stored);
        $this->assertStringContainsString('&lt;script&gt;', $stored);
        $this->assertStringContainsString('{{contractor_name}}', $stored);
    }

    public function test_a_form_created_without_document_text_still_works_as_before(): void
    {
        AiProvider::factory()->active()->create();

        $this->fakeToolCallThenAnswer('design_form_template_draft', [
            'title' => 'فرم ساده',
            'fields' => [['field_key' => 'subject', 'label' => 'موضوع', 'field_type' => 'text']],
        ]);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'فرم ساده بساز')
            ->call('send');

        $this->assertNull(FormTemplate::firstOrFail()->document_template);
        $this->assertFalse($this->toolResultPayload()['document_template_saved']);
    }

    public function test_set_form_document_template_fills_an_existing_form_without_touching_its_fields(): void
    {
        AiProvider::factory()->active()->create();
        $template = FormTemplate::factory()->create(['title' => 'قرارداد اجاره', 'document_template' => null]);
        $field = FormField::factory()->create(['form_template_id' => $template->id, 'field_key' => 'tenant_name']);

        $this->fakeToolCallThenAnswer('set_form_document_template', [
            'form_template_name' => 'قرارداد اجاره',
            'document_template' => 'مستأجر: {{tenant_name}}',
        ]);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'متن قرارداد اجاره رو روی فرمش بذار')
            ->call('send')
            ->call('confirmPendingAction');

        $this->assertSame('<p>مستأجر: {{tenant_name}}</p>', $template->fresh()->document_template);
        $this->assertSame(1, $template->fields()->count());
        $this->assertModelExists($field->fresh());
    }

    public function test_set_form_document_template_will_not_overwrite_an_existing_document_without_confirmation(): void
    {
        AiProvider::factory()->active()->create();
        $template = FormTemplate::factory()->create(['title' => 'قرارداد اجاره', 'document_template' => '<p>متن قدیمی</p>']);
        FormField::factory()->create(['form_template_id' => $template->id, 'field_key' => 'tenant_name']);

        $this->fakeToolCallThenAnswer('set_form_document_template', [
            'form_template_name' => 'قرارداد اجاره',
            'document_template' => 'مستأجر: {{tenant_name}}',
        ]);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'متن جدید بذار')
            ->call('send')
            ->call('confirmPendingAction');

        $this->assertSame('<p>متن قدیمی</p>', $template->fresh()->document_template);
        $this->assertTrue($this->toolResultPayload()['needs_confirmation']);
    }

    public function test_set_form_document_template_replaces_when_the_user_confirmed(): void
    {
        AiProvider::factory()->active()->create();
        $template = FormTemplate::factory()->create(['title' => 'قرارداد اجاره', 'document_template' => '<p>متن قدیمی</p>']);
        FormField::factory()->create(['form_template_id' => $template->id, 'field_key' => 'tenant_name']);

        $this->fakeToolCallThenAnswer('set_form_document_template', [
            'form_template_name' => 'قرارداد اجاره',
            'document_template' => 'مستأجر: {{tenant_name}}',
            'replace_existing' => true,
        ]);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'بله جایگزین کن')
            ->call('send')
            ->call('confirmPendingAction');

        $this->assertSame('<p>مستأجر: {{tenant_name}}</p>', $template->fresh()->document_template);
    }

    public function test_set_form_document_template_refuses_a_placeholder_that_is_not_one_of_the_forms_fields(): void
    {
        AiProvider::factory()->active()->create();
        $template = FormTemplate::factory()->create(['title' => 'قرارداد اجاره', 'document_template' => null]);
        FormField::factory()->create(['form_template_id' => $template->id, 'field_key' => 'tenant_name']);

        $this->fakeToolCallThenAnswer('set_form_document_template', [
            'form_template_name' => 'قرارداد اجاره',
            'document_template' => 'مستأجر: {{landlord_name}}',
        ]);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'متن بذار')
            ->call('send')
            ->call('confirmPendingAction');

        $this->assertNull($template->fresh()->document_template);
        $this->assertStringContainsString('landlord_name', $this->toolResultPayload()['error']);
    }

    public function test_set_form_document_template_needs_the_form_templates_update_permission(): void
    {
        AiProvider::factory()->active()->create();
        $template = FormTemplate::factory()->create(['title' => 'قرارداد اجاره', 'document_template' => null]);
        FormField::factory()->create(['form_template_id' => $template->id, 'field_key' => 'tenant_name']);

        $this->artisan('access-control:sync');
        $role = Role::create(['name' => 'بدون ویرایش قالب فرم']);
        $role->givePermissionTo(Permission::whereIn('name', ['ai-assistant.access', 'ai-assistant.report', 'ai-assistant.act'])->orWhere('name', 'like', '%.view_any')->orWhere('name', 'tasks.create')->get());
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        $this->fakeToolCallThenAnswer('set_form_document_template', [
            'form_template_name' => 'قرارداد اجاره',
            'document_template' => 'مستأجر: {{tenant_name}}',
        ]);

        Livewire::test(AiAssistant::class)
            ->set('body', 'متن بذار')
            ->call('send')
            ->call('confirmPendingAction');

        $this->assertNull($template->fresh()->document_template);
    }
}
