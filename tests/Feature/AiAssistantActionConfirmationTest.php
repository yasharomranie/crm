<?php

namespace Tests\Feature;

use App\Enums\AiMessageRole;
use App\Filament\Pages\AiAssistant;
use App\Models\AiConversation;
use App\Models\AiProvider;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * An action that changes data must not run on the model's say-so alone: it
 * is parked on the conversation and only runs when the user confirms it.
 */
class AiAssistantActionConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsActionUser(): User
    {
        $this->artisan('access-control:sync');

        $role = Role::create(['name' => 'اقدام‌گر '.uniqid()]);
        $role->givePermissionTo(Permission::whereIn('name', [
            'ai-assistant.access', 'ai-assistant.report', 'ai-assistant.act', 'tasks.create', 'tasks.view_any',
        ])->get());

        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function fakeCreateTask(): void
    {
        Http::fakeSequence()
            ->push(['choices' => [['message' => [
                'role' => 'assistant',
                'content' => null,
                'tool_calls' => [[
                    'id' => 'call_1',
                    'type' => 'function',
                    'function' => ['name' => 'create_task', 'arguments' => json_encode(['title' => 'پیگیری بیمه'])],
                ]],
            ]]]])
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'منتظر تأیید شما هستم.']]]]);
    }

    private function toolMessage(): array
    {
        return json_decode(AiConversation::sole()->messages->firstWhere('role', AiMessageRole::Tool)->content, true);
    }

    public function test_the_task_is_not_created_until_the_user_confirms(): void
    {
        AiProvider::factory()->active()->create();
        $this->fakeCreateTask();
        $this->actingAsActionUser();

        Livewire::test(AiAssistant::class)->set('body', 'وظیفه پیگیری بیمه بساز')->call('send');

        $this->assertSame(0, Task::count());
        $this->assertSame('awaiting_user_confirmation', $this->toolMessage()['status']);
        $this->assertCount(1, AiConversation::sole()->pending_actions);
        $this->assertStringContainsString('پیگیری بیمه', AiConversation::sole()->pending_actions[0]['summary']);
    }

    public function test_confirming_runs_the_action_and_records_the_real_result(): void
    {
        AiProvider::factory()->active()->create();
        $this->fakeCreateTask();
        $this->actingAsActionUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'وظیفه پیگیری بیمه بساز')
            ->call('send')
            ->call('confirmPendingAction', 0);

        $this->assertDatabaseHas('tasks', ['title' => 'پیگیری بیمه']);
        $this->assertNull(AiConversation::sole()->pending_actions);
        $this->assertArrayNotHasKey('status', $this->toolMessage());
    }

    public function test_rejecting_discards_the_action(): void
    {
        AiProvider::factory()->active()->create();
        $this->fakeCreateTask();
        $this->actingAsActionUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'وظیفه پیگیری بیمه بساز')
            ->call('send')
            ->call('cancelPendingAction', 0);

        $this->assertSame(0, Task::count());
        $this->assertNull(AiConversation::sole()->pending_actions);
        $this->assertSame('cancelled_by_user', $this->toolMessage()['status']);
    }

    public function test_confirming_a_missing_action_does_nothing(): void
    {
        AiProvider::factory()->active()->create();
        $this->fakeCreateTask();
        $this->actingAsActionUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'وظیفه پیگیری بیمه بساز')
            ->call('send')
            ->call('confirmPendingAction', 5);

        $this->assertSame(0, Task::count());
        $this->assertCount(1, AiConversation::sole()->pending_actions);
    }

    public function test_someone_else_cannot_confirm_an_action_in_a_conversation_that_is_not_theirs(): void
    {
        AiProvider::factory()->active()->create();
        $this->fakeCreateTask();
        $this->actingAsActionUser();

        Livewire::test(AiAssistant::class)->set('body', 'وظیفه پیگیری بیمه بساز')->call('send');
        $conversationId = AiConversation::sole()->id;

        $this->actingAsActionUser();

        Livewire::test(AiAssistant::class)
            ->set('conversationId', $conversationId)
            ->call('confirmPendingAction', 0);

        $this->assertSame(0, Task::count());
        $this->assertCount(1, AiConversation::sole()->pending_actions);
    }
}
