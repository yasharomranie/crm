<?php

namespace Tests\Feature;

use App\Enums\AiMessageRole;
use App\Enums\TaskStatus;
use App\Filament\Pages\AiAssistant;
use App\Models\AiConversation;
use App\Models\AiProvider;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AiAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_sending_a_message_without_an_active_provider_does_not_call_out(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create());

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

        $this->actingAs(User::factory()->create());

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

        $this->actingAs(User::factory()->create());

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

        $this->actingAs(User::factory()->create());

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
}
