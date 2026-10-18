<?php

namespace Tests\Feature;

use App\Domain\Ai\AiToolAccess;
use App\Domain\Ai\AssistantAlertDigestService;
use App\Enums\AiMessageRole;
use App\Enums\TicketStatus;
use App\Filament\Pages\AiAssistant;
use App\Filament\Pages\AiAssistantUsageDashboard;
use App\Filament\Resources\AiProviders\Pages\ManageAiProviders;
use App\Models\AiConversation;
use App\Models\AiProvider;
use App\Models\Guest;
use App\Models\GuestTicket;
use App\Models\User;
use App\Notifications\AssistantAlertsDigestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AiAssistantGrowthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<string>  $permissions
     */
    private function actingAsUserWith(array $permissions): User
    {
        $this->artisan('access-control:sync');

        $role = Role::create(['name' => 'نقش آزمایشی '.uniqid()]);
        $role->givePermissionTo(Permission::whereIn('name', ['ai-assistant.access', 'ai-assistant.report', ...$permissions])->get());

        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    public function test_the_tokens_the_provider_reports_are_stored_on_the_answer(): void
    {
        AiProvider::factory()->active()->create();
        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'سلام']]],
            'usage' => ['prompt_tokens' => 120, 'completion_tokens' => 30],
        ])]);
        $this->actingAsUserWith([]);

        Livewire::test(AiAssistant::class)->set('body', 'سلام')->call('send');

        $answer = AiConversation::sole()->messages->firstWhere('role', AiMessageRole::Assistant);

        $this->assertSame(120, $answer->prompt_tokens);
        $this->assertSame(30, $answer->completion_tokens);
    }

    public function test_a_provider_that_reports_no_usage_leaves_the_tokens_empty(): void
    {
        AiProvider::factory()->active()->create();
        Http::fake(['*' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => 'سلام']]]])]);
        $this->actingAsUserWith([]);

        Livewire::test(AiAssistant::class)->set('body', 'سلام')->call('send');

        $this->assertNull(AiConversation::sole()->messages->firstWhere('role', AiMessageRole::Assistant)->prompt_tokens);
    }

    public function test_the_usage_dashboard_summarises_the_management_assistant(): void
    {
        $user = $this->admin();
        $conversation = AiConversation::create(['user_id' => $user->id]);
        $conversation->messages()->create(['role' => AiMessageRole::User, 'content' => 'سؤال']);
        $conversation->messages()->create(['role' => AiMessageRole::Assistant, 'content' => 'پاسخ', 'prompt_tokens' => 100, 'completion_tokens' => 40, 'feedback' => 'up']);
        $conversation->messages()->create(['role' => AiMessageRole::Assistant, 'content' => 'پاسخ دوم', 'prompt_tokens' => 50, 'completion_tokens' => 10, 'feedback' => 'down']);
        activity('ai_assistant')->withProperties(['tool' => 'get_task_summary'])->log('x');
        activity('ai_assistant')->withProperties(['tool' => 'get_task_summary'])->log('x');
        activity('ai_assistant')->withProperties(['tool' => 'get_crm_summary'])->log('x');

        $stats = Livewire::actingAs($user)->test(AiAssistantUsageDashboard::class)->instance()->getMainAssistantStats();

        $this->assertSame(1, $stats['questions']);
        $this->assertSame(150, $stats['prompt_tokens']);
        $this->assertSame(50, $stats['completion_tokens']);
        $this->assertSame(1, $stats['feedback_up']);
        $this->assertSame(1, $stats['feedback_down']);
        $this->assertSame(['tool' => 'get_task_summary', 'calls' => 2], $stats['top_tools'][0]);
    }

    public function test_the_ticketing_sales_tool_needs_the_orders_permission(): void
    {
        $this->assertFalse(AiToolAccess::allowsTool($this->actingAsUserWith([]), 'get_ticketing_sales_summary'));
        $this->assertTrue(AiToolAccess::allowsTool($this->actingAsUserWith(['orders.view_any']), 'get_ticketing_sales_summary'));
    }

    public function test_the_ticketing_sales_tool_returns_the_report(): void
    {
        AiProvider::factory()->active()->create();
        Http::fakeSequence()
            ->push(['choices' => [['message' => [
                'role' => 'assistant',
                'content' => null,
                'tool_calls' => [['id' => 'c1', 'type' => 'function', 'function' => ['name' => 'get_ticketing_sales_summary', 'arguments' => json_encode(['days' => 7])]]],
            ]]]])
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'فروشی نبود.']]]]);
        $this->actingAsUserWith(['orders.view_any']);

        Livewire::test(AiAssistant::class)->set('body', 'فروش بلیت این هفته؟')->call('send');

        $payload = json_decode(AiConversation::sole()->messages->firstWhere('role', AiMessageRole::Tool)->content, true);

        $this->assertSame(0, $payload['sales']['orders']);
        $this->assertArrayHasKey('unused_or_expired', $payload);
        $this->assertArrayHasKey('reconciliation', $payload);
    }

    public function test_critical_alerts_are_pushed_to_admins_once_a_day(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $guest = Guest::factory()->create();
        GuestTicket::create(['guest_id' => $guest->id, 'subject' => 'اینترنت اتاق قطع است', 'status' => TicketStatus::Open, 'sla_due_at' => now()->subHours(5)]);

        $notified = app(AssistantAlertDigestService::class)->notifyDailyDigest();

        $this->assertSame(1, $notified);
        Notification::assertSentTo($admin, AssistantAlertsDigestNotification::class);
    }

    public function test_nothing_is_pushed_on_a_quiet_day(): void
    {
        Notification::fake();
        $this->admin();

        $this->assertSame(0, app(AssistantAlertDigestService::class)->notifyDailyDigest());
        Notification::assertNothingSent();
    }

    public function test_the_connection_test_reports_success_and_failure(): void
    {
        $provider = AiProvider::factory()->active()->create();
        $admin = $this->admin();

        Http::fake(['*' => Http::sequence()
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'آماده']]]])
            ->push(['error' => ['message' => 'Invalid API key']], 401)]);

        Livewire::actingAs($admin)->test(ManageAiProviders::class)
            ->callTableAction('testConnection', $provider)
            ->assertNotified('اتصال برقرار است');

        Livewire::actingAs($admin)->test(ManageAiProviders::class)
            ->callTableAction('testConnection', $provider)
            ->assertNotified('اتصال برقرار نشد');
    }
}
