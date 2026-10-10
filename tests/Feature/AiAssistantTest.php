<?php

namespace Tests\Feature;

use App\Enums\AiMessageRole;
use App\Enums\ApproverType;
use App\Enums\FormSubmissionStatus;
use App\Enums\TaskStatus;
use App\Filament\Pages\AiAssistant;
use App\Models\AiAssistantModelSetting;
use App\Models\AiConversation;
use App\Models\AiProvider;
use App\Models\Department;
use App\Models\FormSubmission;
use App\Models\FormTemplate;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Morilog\Jalali\Jalalian;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AiAssistantTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Logs in a user granted "ai-assistant.report", "ai-assistant.act", and
     * the two normal resource permissions that design_workflow_draft /
     * design_form_template_draft additionally require (see executeTool()'s
     * "never grant more power than the user already has" check) - the
     * full-access role most of these tests need, since they exercise
     * reporting, create_task, and the design tools. Tests for the
     * permission gating itself build their own narrower roles.
     */
    private function actingAsFullyPermittedAiUser(): User
    {
        $this->artisan('access-control:sync');

        $role = Role::create(['name' => 'مدیر کامل هوش مصنوعی']);
        $role->givePermissionTo(Permission::whereIn('name', [
            'ai-assistant.access', 'ai-assistant.report', 'ai-assistant.act',
            'workflow-definitions.create', 'form-templates.create',
        ])->get());

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

    /**
     * Regression guard for the gap a chat-only table answer left: a
     * breakdown-shaped reporting tool's result must also reach the UI as a
     * chart (see HasAiAssistantEngine::buildChartForTool()), attached only
     * to the FINAL assistant message - never to the earlier one that only
     * requested the tool call, since that round has no result yet to chart.
     */
    public function test_a_chartable_tool_result_attaches_a_bar_chart_to_the_final_answer_only(): void
    {
        AiProvider::factory()->active()->create();
        Task::factory()->count(2)->create(['status' => TaskStatus::Todo]);
        Task::factory()->create(['status' => TaskStatus::Done]);

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
                    'message' => ['role' => 'assistant', 'content' => 'وضعیت وظایف رو بررسی کردم.'],
                ]],
            ]);

        $this->actingAsFullyPermittedAiUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'وضعیت وظایف رو با نمودار نشونم بده')
            ->call('send');

        $assistantMessages = AiConversation::sole()->messages->where('role', AiMessageRole::Assistant)->values();

        // The round that only requested the tool call has no result yet to
        // chart - it must never carry one.
        $this->assertNull($assistantMessages[0]->chart);

        $chart = $assistantMessages[1]->chart;
        $this->assertSame('bar', $chart['type']);
        $this->assertSame('وظایف بر اساس وضعیت', $chart['title']);
        $this->assertSame(3, array_sum($chart['datasets'][0]['data']));
        $this->assertContains('در صف انجام', $chart['labels']);
        $this->assertContains('تکمیل‌شده', $chart['labels']);
    }

    /**
     * Not every tool's result is chart-worthy (a detail lookup, a raw
     * search) - this must never force a meaningless/empty chart onto one
     * that returns nothing to plot (here, no workflow decisions exist at
     * all yet, so get_approver_performance returns only a "message" key).
     */
    public function test_a_tool_result_with_no_chartable_data_leaves_the_final_message_without_a_chart(): void
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
                            'function' => ['name' => 'get_approver_performance', 'arguments' => '{}'],
                        ]],
                    ],
                ]],
            ])
            ->push([
                'choices' => [[
                    'message' => ['role' => 'assistant', 'content' => 'هنوز هیچ تأیید یا ردی ثبت نشده.'],
                ]],
            ]);

        $this->actingAsFullyPermittedAiUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'عملکرد تأییدکننده‌ها چطوره؟')
            ->call('send');

        $finalMessage = AiConversation::sole()->messages->where('role', AiMessageRole::Assistant)->last();

        $this->assertNull($finalMessage->chart);
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

    public function test_the_create_task_tool_assigns_an_exact_matching_department(): void
    {
        AiProvider::factory()->active()->create();
        $department = Department::factory()->create(['name' => 'دپارتمان فروش']);

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
                                'arguments' => json_encode([
                                    'title' => 'پیگیری مشتری',
                                    'department_name' => 'دپارتمان فروش',
                                ]),
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
            ->set('body', 'یک وظیفه برای دپارتمان فروش بساز')
            ->call('send');

        $this->assertDatabaseHas('tasks', ['title' => 'پیگیری مشتری', 'department_id' => $department->id]);
    }

    /**
     * Regression test: create_task used to silently create the task with an
     * empty department whenever the model's department_name didn't match
     * anything - the user only ever saw a "success" message and had to
     * notice the department field was blank. It must now refuse instead.
     */
    public function test_the_create_task_tool_refuses_instead_of_silently_dropping_an_unmatched_department(): void
    {
        AiProvider::factory()->active()->create();
        Department::factory()->create(['name' => 'دپارتمان فروش']);

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
                                'arguments' => json_encode([
                                    'title' => 'پیگیری مشتری',
                                    'department_name' => 'دپارتمانی که اصلاً وجود ندارد',
                                ]),
                            ],
                        ]],
                    ],
                ]],
            ])
            ->push([
                'choices' => [[
                    'message' => ['role' => 'assistant', 'content' => 'چنین دپارتمانی پیدا نشد.'],
                ]],
            ]);

        $this->actingAsFullyPermittedAiUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'یک وظیفه برای دپارتمانی که اصلاً وجود ندارد بساز')
            ->call('send');

        $this->assertDatabaseMissing('tasks', ['title' => 'پیگیری مشتری']);

        $toolMessage = AiConversation::sole()->messages->firstWhere('role', AiMessageRole::Tool);
        $payload = json_decode($toolMessage->content, true);
        $this->assertArrayHasKey('error', $payload);
        $this->assertContains('دپارتمان فروش', $payload['available_departments']);
    }

    /**
     * Regression test: due_at itself was always being saved correctly, but
     * the tool used to only hand the model back the Gregorian date, leaving
     * the model to convert it to Jalali itself for its reply - and that
     * manual conversion was consistently wrong (e.g. reporting "۱۵ شهریور"
     * for a date that was really "۲۴ شهریور"). The tool must now do that
     * conversion itself and hand back a ready-to-quote Jalali string.
     */
    public function test_the_create_task_tool_returns_a_pre_converted_jalali_due_date_so_the_model_never_converts_it_itself(): void
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
                                'arguments' => json_encode([
                                    'title' => 'گزارش فردا',
                                    'due_at' => now()->addDay()->format('Y-m-d H:i'),
                                ]),
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
            ->set('body', 'یک وظیفه برای فردا بساز')
            ->call('send');

        $task = Task::sole();
        $toolMessage = AiConversation::sole()->messages->firstWhere('role', AiMessageRole::Tool);
        $payload = json_decode($toolMessage->content, true);

        $this->assertSame(
            Jalalian::fromCarbon($task->due_at)->format('%d %B %Y'),
            $payload['due_at_jalali'],
        );
    }

    public function test_the_create_task_tool_refuses_an_unparseable_due_date_instead_of_creating_a_task_with_a_wrong_date(): void
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
                                'arguments' => json_encode([
                                    'title' => 'پیگیری مشتری',
                                    'due_at' => 'یه روز نامعلوم',
                                ]),
                            ],
                        ]],
                    ],
                ]],
            ])
            ->push([
                'choices' => [[
                    'message' => ['role' => 'assistant', 'content' => 'تاریخ نامعتبر بود.'],
                ]],
            ]);

        $this->actingAsFullyPermittedAiUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'یک وظیفه با یه تاریخ عجیب بساز')
            ->call('send');

        $this->assertDatabaseMissing('tasks', ['title' => 'پیگیری مشتری']);
    }

    public function test_the_system_prompt_tells_the_model_todays_real_date_so_relative_dates_resolve_correctly(): void
    {
        AiProvider::factory()->active()->create();

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['role' => 'assistant', 'content' => 'باشه.']],
                ],
            ]),
        ]);

        $this->actingAsFullyPermittedAiUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'فردا رو یادم بنداز')
            ->call('send');

        Http::assertSent(function ($request) {
            $systemMessage = collect($request->data()['messages'])->firstWhere('role', 'system');

            return str_contains($systemMessage['content'], now()->format('Y-m-d'));
        });
    }

    public function test_design_workflow_draft_creates_an_inactive_workflow_with_ordered_steps(): void
    {
        AiProvider::factory()->active()->create();
        $techOfficer = User::factory()->create(['name' => 'زهرا احمدی']);

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
                                'name' => 'design_workflow_draft',
                                'arguments' => json_encode([
                                    'name' => 'گردش‌کار درخواست مرخصی',
                                    'steps' => [
                                        ['name' => 'تأیید دفتر فنی', 'approver_type' => 'specific_user', 'approver_user_name' => 'زهرا احمدی'],
                                        ['name' => 'تأیید مدیر دپارتمان', 'approver_type' => 'department_manager'],
                                    ],
                                ]),
                            ],
                        ]],
                    ],
                ]],
            ])
            ->push([
                'choices' => [[
                    'message' => ['role' => 'assistant', 'content' => 'گردش‌کار به‌صورت پیش‌نویس ساخته شد.'],
                ]],
            ]);

        $this->actingAsFullyPermittedAiUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'یه گردش‌کار برای درخواست مرخصی بساز')
            ->call('send');

        $workflow = WorkflowDefinition::sole();
        $this->assertSame('گردش‌کار درخواست مرخصی', $workflow->name);
        $this->assertFalse($workflow->is_active);
        $this->assertSame(2, $workflow->steps()->count());

        $firstStep = $workflow->steps()->where('step_order', 1)->sole();
        $this->assertSame($techOfficer->id, $firstStep->approver_user_id);

        $secondStep = $workflow->steps()->where('step_order', 2)->sole();
        $this->assertSame(ApproverType::DepartmentManager, $secondStep->approver_type);
    }

    public function test_design_workflow_draft_refuses_instead_of_creating_anything_when_an_approver_user_is_unmatched(): void
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
                                'name' => 'design_workflow_draft',
                                'arguments' => json_encode([
                                    'name' => 'گردش‌کار درخواست مرخصی',
                                    'steps' => [
                                        ['name' => 'تأیید دفتر فنی', 'approver_type' => 'specific_user', 'approver_user_name' => 'کاربری که وجود ندارد'],
                                    ],
                                ]),
                            ],
                        ]],
                    ],
                ]],
            ])
            ->push([
                'choices' => [[
                    'message' => ['role' => 'assistant', 'content' => 'چنین کاربری پیدا نشد.'],
                ]],
            ]);

        $this->actingAsFullyPermittedAiUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'یه گردش‌کار برای درخواست مرخصی بساز')
            ->call('send');

        $this->assertSame(0, WorkflowDefinition::count());

        $toolMessage = AiConversation::sole()->messages->firstWhere('role', AiMessageRole::Tool);
        $payload = json_decode($toolMessage->content, true);
        $this->assertArrayHasKey('error', $payload);
    }

    public function test_design_form_template_draft_creates_an_inactive_form_with_a_step_scoped_field(): void
    {
        AiProvider::factory()->active()->create();
        $workflow = WorkflowDefinition::factory()->create();
        $step = WorkflowStep::factory()->create(['workflow_definition_id' => $workflow->id, 'name' => 'تأیید دفتر فنی']);

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
                                'name' => 'design_form_template_draft',
                                'arguments' => json_encode([
                                    'title' => 'فرم درخواست مرخصی',
                                    'workflow_definition_id' => $workflow->id,
                                    'fields' => [
                                        ['field_key' => 'reason', 'label' => 'دلیل مرخصی', 'field_type' => 'textarea', 'is_required' => true],
                                        ['field_key' => 'tech_note', 'label' => 'نظر دفتر فنی', 'field_type' => 'text', 'workflow_step_name' => 'تأیید دفتر فنی'],
                                    ],
                                ]),
                            ],
                        ]],
                    ],
                ]],
            ])
            ->push([
                'choices' => [[
                    'message' => ['role' => 'assistant', 'content' => 'فرم به‌صورت پیش‌نویس ساخته شد.'],
                ]],
            ]);

        $this->actingAsFullyPermittedAiUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'یه فرم درخواست مرخصی با نظر دفتر فنی بساز')
            ->call('send');

        $template = FormTemplate::sole();
        $this->assertSame('فرم درخواست مرخصی', $template->title);
        $this->assertFalse($template->is_active);
        $this->assertSame($workflow->id, $template->workflow_definition_id);
        $this->assertSame(['reason'], $template->generalFields->pluck('field_key')->all());

        $techNoteField = $template->fields()->where('field_key', 'tech_note')->sole();
        $this->assertSame($step->id, $techNoteField->workflow_step_id);
    }

    public function test_design_form_template_draft_refuses_an_invalid_field_key_instead_of_creating_a_broken_form(): void
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
                                'name' => 'design_form_template_draft',
                                'arguments' => json_encode([
                                    'title' => 'فرم تست',
                                    'fields' => [
                                        ['field_key' => 'دلیل مرخصی', 'label' => 'دلیل مرخصی', 'field_type' => 'text'],
                                    ],
                                ]),
                            ],
                        ]],
                    ],
                ]],
            ])
            ->push([
                'choices' => [[
                    'message' => ['role' => 'assistant', 'content' => 'کلید فیلد نامعتبر بود.'],
                ]],
            ]);

        $this->actingAsFullyPermittedAiUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'یه فرم تست بساز')
            ->call('send');

        $this->assertSame(0, FormTemplate::count());
    }

    public function test_a_report_only_user_cannot_get_design_workflow_draft_to_execute(): void
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
                                'name' => 'design_workflow_draft',
                                'arguments' => json_encode(['name' => 'گردش‌کار تست', 'steps' => [
                                    ['name' => 'مرحلهٔ ۱', 'approver_type' => 'department_manager'],
                                ]]),
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
        $role = Role::create(['name' => 'گزارش‌گیر دستیار هوشمند دوم']);
        $role->givePermissionTo(Permission::whereIn('name', ['ai-assistant.access', 'ai-assistant.report'])->get());
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        Livewire::test(AiAssistant::class)
            ->set('body', 'یه گردش‌کار تست بساز')
            ->call('send');

        $this->assertSame(0, WorkflowDefinition::count());
    }

    /**
     * Even with "ai-assistant.act" granted, design_workflow_draft must
     * still refuse a user who lacks the normal "workflow-definitions.create"
     * resource permission - the AI must never let someone build through
     * chat what they couldn't build through the regular panel.
     */
    public function test_ai_assistant_act_alone_is_not_enough_to_design_a_workflow_without_the_underlying_resource_permission(): void
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
                                'name' => 'design_workflow_draft',
                                'arguments' => json_encode(['name' => 'گردش‌کار تست', 'steps' => [
                                    ['name' => 'مرحلهٔ ۱', 'approver_type' => 'department_manager'],
                                ]]),
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
        $role = Role::create(['name' => 'اقدام‌گر بدون دسترسی گردش‌کار']);
        // "ai-assistant.act" without "workflow-definitions.create".
        $role->givePermissionTo(Permission::whereIn('name', ['ai-assistant.access', 'ai-assistant.act'])->get());
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        Livewire::test(AiAssistant::class)
            ->set('body', 'یه گردش‌کار تست بساز')
            ->call('send');

        $this->assertSame(0, WorkflowDefinition::count());
    }

    public function test_sending_a_message_uses_the_preferred_model_when_one_is_set(): void
    {
        AiProvider::factory()->active()->create(['default_model' => 'gpt-4o-mini']);
        AiAssistantModelSetting::setModelFor('ai-assistant', 'gpt-4o');

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'سلام!']]],
            ]),
        ]);

        $this->actingAsFullyPermittedAiUser();

        Livewire::test(AiAssistant::class)->set('body', 'سلام')->call('send');

        Http::assertSent(fn ($request) => $request->data()['model'] === 'gpt-4o');
    }

    public function test_sending_a_message_falls_back_to_the_providers_default_model_when_none_is_preferred(): void
    {
        AiProvider::factory()->active()->create(['default_model' => 'gpt-4o-mini']);

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'سلام!']]],
            ]),
        ]);

        $this->actingAsFullyPermittedAiUser();

        Livewire::test(AiAssistant::class)->set('body', 'سلام')->call('send');

        Http::assertSent(fn ($request) => $request->data()['model'] === 'gpt-4o-mini');
    }

    public function test_loading_model_options_populates_them_from_the_providers_live_list(): void
    {
        AiProvider::factory()->active()->create();

        Http::fake([
            '*/models' => Http::response(['data' => [['id' => 'gpt-4o'], ['id' => 'gpt-4o-mini']]]),
        ]);

        $this->actingAsFullyPermittedAiUser();

        $component = Livewire::test(AiAssistant::class)->call('loadModelOptions');

        $this->assertSame(['gpt-4o' => 'gpt-4o', 'gpt-4o-mini' => 'gpt-4o-mini'], $component->get('modelOptions'));
    }

    public function test_updating_the_preferred_model_persists_it(): void
    {
        AiProvider::factory()->active()->create();

        $this->actingAsFullyPermittedAiUser();

        Livewire::test(AiAssistant::class)->call('updatePreferredModel', 'gpt-4o');

        $this->assertSame('gpt-4o', AiAssistantModelSetting::modelFor('ai-assistant'));
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

        $this->assertSame('۱', AiAssistant::getNavigationBadge());
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
