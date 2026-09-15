<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Filament\Pages\AiAssistant;
use App\Filament\Pages\Dashboard;
use App\Livewire\AiAssistantWidget;
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
 * The floating panel-wide mascot (AiAssistantWidget) shares its entire
 * conversation/tool engine with the full-page AiAssistant via
 * HasAiAssistantEngine - these tests exist to prove that sharing actually
 * works end-to-end (not just that the trait compiles), and that the
 * widget's own extra rules (never render without access, never render on
 * the dedicated page) hold.
 */
class AiAssistantWidgetTest extends TestCase
{
    use RefreshDatabase;

    private function userWithAccess(): User
    {
        $this->artisan('access-control:sync');

        $role = Role::create(['name' => 'دسترسی کامل دستیار ویجت']);
        $role->givePermissionTo(Permission::whereIn('name', [
            'ai-assistant.access', 'ai-assistant.report', 'ai-assistant.act',
        ])->get());

        // Explicit is_active: true - a factory-created instance never
        // reflects the users table's own is_active DEFAULT (Eloquent only
        // hydrates attributes it was actually given), and this test is the
        // one place in the suite that visits a panel page via a real HTTP
        // request, so it's the one place canAccessPanel()'s is_active
        // check is actually exercised.
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    public function test_the_bubble_renders_for_a_user_with_access(): void
    {
        $this->actingAs($this->userWithAccess());

        Livewire::test(AiAssistantWidget::class)
            ->assertSeeHtml('ai-widget-bubble');
    }

    public function test_nothing_renders_for_a_user_without_access(): void
    {
        $this->artisan('access-control:sync');
        $role = Role::create(['name' => 'بدون دسترسی دستیار ویجت']);
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        Livewire::test(AiAssistantWidget::class)
            ->assertDontSeeHtml('ai-widget-bubble');
    }

    public function test_the_widget_is_hidden_on_the_dedicated_ai_assistant_page_but_visible_on_other_panel_pages(): void
    {
        $this->actingAs($this->userWithAccess());

        $this->get(AiAssistant::getUrl())
            ->assertOk()
            ->assertDontSee('ai-widget-bubble', false);

        $this->get(Dashboard::getUrl())
            ->assertOk()
            ->assertSee('ai-widget-bubble', false);
    }

    /**
     * Proves the shared engine trait actually works identically from the
     * widget, not just from the full page - same fake HTTP sequence and
     * assertions as AiAssistantTest's equivalent full-page test.
     */
    public function test_the_widget_can_send_a_message_and_run_a_tool_call(): void
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
                    'message' => ['role' => 'assistant', 'content' => 'دو تا وظیفهٔ باز دارید.'],
                ]],
            ]);

        $this->actingAs($this->userWithAccess());

        Livewire::test(AiAssistantWidget::class)
            ->set('body', 'چند تا وظیفه باز داریم؟')
            ->call('send');

        Http::assertSentCount(2);

        $finalMessage = AiConversation::sole()->messages->last();
        $this->assertSame('دو تا وظیفهٔ باز دارید.', $finalMessage->content);
    }
}
