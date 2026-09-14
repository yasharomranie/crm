<?php

namespace Tests\Feature;

use App\Enums\AiMessageRole;
use App\Enums\FormSubmissionStatus;
use App\Enums\TaskStatus;
use App\Filament\Pages\AiAssistant;
use App\Models\AiConversation;
use App\Models\AiProvider;
use App\Models\FormSubmission;
use App\Models\FormTemplate;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AiAssistantTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Logs in a user granted both "ai-assistant.report" and
     * "ai-assistant.act" - the full-access role most of these tests need,
     * since they exercise both the reporting tools and create_task. Tests
     * for the permission gating itself build their own narrower roles.
     */
    private function actingAsFullyPermittedAiUser(): User
    {
        $this->artisan('access-control:sync');

        $role = Role::create(['name' => 'مدیر کامل هوش مصنوعی']);
        $role->givePermissionTo(Permission::whereIn('name', ['ai-assistant.access', 'ai-assistant.report', 'ai-assistant.act'])->get());

        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user);

        return $user;
    }

    public function test_sending_a_message_without_an_active_provider_does_not_call_out(): void
    {
        Http::fake();

        $this->actingAsFullyPermittedAiUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'سلام')
            ->call('send');

        Http::assertNothingSent();
    }

    public function test_a_simple_question_gets_a_direct_answer_with_no_tool_calls(): void
    {
        AiProvider::factory()->active()->create();

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['role' => 'assistant', 'content' => 'سلام! چطور می‌توانم کمک کنم؟']],
                ],
            ]),
        ]);

        $this->actingAsFullyPermittedAiUser();

        $component = Livewire::test(AiAssistant::class)
            ->set('body', 'سلام')
            ->call('send');

        $conversationId = $component->get('conversationId');
        $this->assertNotNull($conversationId);

        $messages = AiConversation::find($conversationId)->messages;

        $this->assertCount(2, $messages);
        $this->assertSame(AiMessageRole::User, $messages[0]->role);
        $this->assertSame('سلام', $messages[0]->content);
        $this->assertSame(AiMessageRole::Assistant, $messages[1]->role);
        $this->assertSame('سلام! چطور می‌توانم کمک کنم؟', $messages[1]->content);

        Http::assertSentCount(1);
    }

    public function test_the_agent_calls_a_tool_then_answers_using_its_result(): void
    {
        AiProvider::factory()->active()->create();
        Task::factory()->count(2)->create(['status' => TaskStatus::Todo]);

        Http::fakeSequence()
            ->push([
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_1',
                            'type' => 'function',
                            'function' => ['name' => 'get_task_summary', 'arguments' => '{}'],
                        ]],
                    ],
                ]],
            ])
            ->push([
                'choices' => [[
                    'message' => ['role' => 'assistant', 'content' => 'در حال حاضر ۲ وظیفه در صف انجام دارید.'],
                ]],
            ]);

        $this->actingAsFullyPermittedAiUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'چند تا وظیفه باز داریم؟')
            ->call('send');

        Http::assertSentCount(2);

        $conversation = AiConversation::sole();
        $roles = $conversation->messages->pluck('role')->all();

        $this->assertSame(
            [AiMessageRole::User, AiMessageRole::Assistant, AiMessageRole::Tool, AiMessageRole::Assistant],
            $roles,
        );

        $toolMessage = $conversation->messages->firstWhere('role', AiMessageRole::Tool);
        $this->assertSame('get_task_summary', $toolMessage->tool_name);
        $this->assertStringContainsString('"overdue_count"', $toolMessage->content);

        $finalMessage = $conversation->messages->last();
        $this->assertSame('در حال حاضر ۲ وظیفه در صف انجام دارید.', $finalMessage->content);
    }

    public function test_the_create_task_tool_actually_creates_a_task(): void
    {
        AiProvider::factory()->active()->create();

        Http::fakeSequence()
            ->push([
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_1',
                            'type' => 'function',
                            'function' => [
                                'name' => 'create_task',
                                'arguments' => json_encode(['title' => 'تماس با تأمین‌کننده']),
                            ],
                        ]],
                    ],
                ]],
            ])
            ->push([
                'choices' => [[
                    'message' => ['role' => 'assistant', 'content' => 'وظیفه ایجاد شد.'],
                ]],
            ]);

        $this->actingAsFullyPermittedAiUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'یک وظیفه برای تماس با تأمین‌کننده بساز')
            ->call('send');

        $this->assertDatabaseHas('tasks', ['title' => 'تماس با تأمین‌کننده']);
    }

    public function test_alerts_flag_overdue_tasks(): void
    {
        Task::factory()->create([
            'status' => TaskStatus::Todo,
            'due_at' => now()->subDay(),
        ]);

        $alerts = AiAssistant::computeAlerts();

        $this->assertNotEmpty(array_filter($alerts, fn ($alert) => str_contains($alert['message'], 'وظیفه')));
    }

    public function test_navigation_badge_reflects_alert_count(): void
    {
        $this->assertNull(AiAssistant::getNavigationBadge());

        Task::factory()->create(['status' => TaskStatus::Todo, 'due_at' => now()->subDay()]);

        $this->assertSame('1', AiAssistant::getNavigationBadge());
    }

    public function test_the_form_submissions_summary_tool_reports_pending_and_sla_breaches(): void
    {
        AiProvider::factory()->active()->create();

        $template = FormTemplate::factory()->create(['title' => 'گزارش روزانه']);

        FormSubmission::factory()->create([
            'form_template_id' => $template->id,
            'status' => FormSubmissionStatus::PendingApproval,
        ]);

        // An in-progress step whose 1-hour SLA has clearly been breached -
        // updated_at is forced back in time since that's the (undeclared,
        // but Eloquent-maintained) "entered this step at" stand-in.
        $step = WorkflowStep::factory()->create(['sla_hours' => 1]);
        $instance = WorkflowInstance::factory()->create(['current_step_id' => $step->id]);
        DB::table('workflow_instances')->where('id', $instance->id)->update(['updated_at' => now()->subHours(5)]);

        Http::fakeSequence()
            ->push([
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_1',
                            'type' => 'function',
                            'function' => ['name' => 'get_form_submissions_summary', 'arguments' => '{}'],
                        ]],
                    ],
                ]],
            ])
            ->push([
                'choices' => [[
                    'message' => ['role' => 'assistant', 'content' => 'یک فرم در انتظار تأیید و یک تأخیر SLA دارید.'],
                ]],
            ]);

        $this->actingAsFullyPermittedAiUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'وضعیت فرم‌ها و کارتابل تأییدات چیه؟')
            ->call('send');

        $toolMessage = AiConversation::sole()->messages->firstWhere('role', AiMessageRole::Tool);
        $this->assertSame('get_form_submissions_summary', $toolMessage->tool_name);

        $payload = json_decode($toolMessage->content, true);

        $this->assertSame(1, $payload['pending_total']);
        $this->assertSame(1, $payload['pending_by_form']['گزارش روزانه']);
        $this->assertSame(1, $payload['sla_breached_count']);
    }

    public function test_the_daily_checklist_compliance_tool_scopes_to_the_named_employee(): void
    {
        AiProvider::factory()->active()->create();

        $template = FormTemplate::factory()->create(['title' => 'چک‌لیست روزانه']);
        $employee = User::factory()->create(['name' => 'زهرا احمدی']);
        $otherEmployee = User::factory()->create(['name' => 'رضا کریمی']);

        FormSubmission::factory()->create([
            'form_template_id' => $template->id,
            'submitted_by' => $employee->id,
            'submitted_at' => now(),
        ]);
        FormSubmission::factory()->create([
            'form_template_id' => $template->id,
            'submitted_by' => $otherEmployee->id,
            'submitted_at' => now(),
        ]);

        Http::fakeSequence()
            ->push([
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_1',
                            'type' => 'function',
                            'function' => [
                                'name' => 'get_daily_checklist_compliance',
                                'arguments' => json_encode(['form_template_name' => 'چک‌لیست', 'user_name' => 'زهرا']),
                            ],
                        ]],
                    ],
                ]],
            ])
            ->push([
                'choices' => [[
                    'message' => ['role' => 'assistant', 'content' => 'زهرا امروز چک‌لیستش را ثبت کرده است.'],
                ]],
            ]);

        $this->actingAsFullyPermittedAiUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'زهرا چند روز این ماه چک‌لیست روزانه رو ثبت نکرده؟')
            ->call('send');

        $toolMessage = AiConversation::sole()->messages->firstWhere('role', AiMessageRole::Tool);
        $payload = json_decode($toolMessage->content, true);

        $this->assertSame('چک‌لیست روزانه', $payload['form']);
        $this->assertCount(1, $payload['compliance']);
        $this->assertSame('زهرا احمدی', $payload['compliance'][0]['user']);
        $this->assertGreaterThanOrEqual(1, $payload['compliance'][0]['submitted_days']);
        // Invariant that holds regardless of which Jalali day the test
        // happens to run on: every expected day is either submitted or
        // reported missed - never both, never neither.
        $this->assertSame(
            $payload['compliance'][0]['expected_days'],
            $payload['compliance'][0]['submitted_days'] + count($payload['compliance'][0]['missed_days'])
        );
    }

    public function test_the_daily_checklist_compliance_tool_returns_an_error_for_an_unknown_form(): void
    {
        AiProvider::factory()->active()->create();

        Http::fakeSequence()
            ->push([
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_1',
                            'type' => 'function',
                            'function' => [
                                'name' => 'get_daily_checklist_compliance',
                                'arguments' => json_encode(['form_template_name' => 'فرم ناموجود']),
                            ],
                        ]],
                    ],
                ]],
            ])
            ->push([
                'choices' => [[
                    'message' => ['role' => 'assistant', 'content' => 'چنین فرمی پیدا نشد.'],
                ]],
            ]);

        $this->actingAsFullyPermittedAiUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'فرم ناموجود رو چک کن')
            ->call('send');

        $toolMessage = AiConversation::sole()->messages->firstWhere('role', AiMessageRole::Tool);
        $this->assertStringContainsString('پیدا نشد', $toolMessage->content);
    }

    public function test_a_user_without_any_ai_permission_gets_no_tools_offered_and_a_direct_answer(): void
    {
        AiProvider::factory()->active()->create();

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['role' => 'assistant', 'content' => 'سلام! چطور می‌توانم کمک کنم؟']],
                ],
            ]),
        ]);

        // A page-access-only user - not granted "ai-assistant.report" or
        // "ai-assistant.act" - should never even be offered a tool.
        $this->artisan('access-control:sync');
        $role = Role::create(['name' => 'مشاهده‌گر دستیار هوشمند']);
        $role->givePermissionTo(Permission::where('name', 'ai-assistant.access')->sole());
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        Livewire::test(AiAssistant::class)
            ->set('body', 'سلام')
            ->call('send');

        Http::assertSent(function ($request) {
            return ! array_key_exists('tools', $request->data());
        });
    }

    public function test_a_report_only_user_cannot_get_create_task_to_execute_even_if_the_model_calls_it(): void
    {
        AiProvider::factory()->active()->create();

        // Simulates a stale/replayed tool_call for "create_task" - a tool
        // that should never even be offered to a report-only user - to
        // prove executeTool()'s own permission check is real, not just the
        // tool list filtering in getToolDefinitions().
        Http::fakeSequence()
            ->push([
                'choices' => [[
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_1',
                            'type' => 'function',
                            'function' => [
                                'name' => 'create_task',
                                'arguments' => json_encode(['title' => 'تماس با تأمین‌کننده']),
                            ],
                        ]],
                    ],
                ]],
            ])
            ->push([
                'choices' => [[
                    'message' => ['role' => 'assistant', 'content' => 'شما اجازهٔ این کار را ندارید.'],
                ]],
            ]);

        $this->artisan('access-control:sync');
        $role = Role::create(['name' => 'گزارش‌گیر دستیار هوشمند']);
        $role->givePermissionTo(Permission::whereIn('name', ['ai-assistant.access', 'ai-assistant.report'])->get());
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        Livewire::test(AiAssistant::class)
            ->set('body', 'یک وظیفه برای تماس با تأمین‌کننده بساز')
            ->call('send');

        $this->assertDatabaseMissing('tasks', ['title' => 'تماس با تأمین‌کننده']);

        $toolMessage = AiConversation::sole()->messages->firstWhere('role', AiMessageRole::Tool);
        $this->assertStringContainsString('اجازه', $toolMessage->content);
    }
}
