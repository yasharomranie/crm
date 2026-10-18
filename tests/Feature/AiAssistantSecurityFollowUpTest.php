<?php

namespace Tests\Feature;

use App\Domain\Ai\AssistantAlertDigestService;
use App\Domain\Ai\AssistantRetention;
use App\Enums\AiMessageRole;
use App\Enums\TaskStatus;
use App\Enums\TicketStatus;
use App\Filament\Pages\AiAssistant;
use App\Filament\Pages\AiAssistantUsageDashboard;
use App\Models\AiConversation;
use App\Models\AiProvider;
use App\Models\Guest;
use App\Models\GuestTicket;
use App\Models\Task;
use App\Models\User;
use App\Notifications\AssistantAlertsDigestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AiAssistantSecurityFollowUpTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<string>  $permissions
     */
    private function userWith(array $permissions, string $name = 'کاربر آزمایشی'): User
    {
        $this->artisan('access-control:sync');

        $role = Role::create(['name' => 'نقش '.uniqid()]);
        $role->givePermissionTo(Permission::whereIn('name', ['ai-assistant.access', 'ai-assistant.report', ...$permissions])->get());

        $user = User::factory()->create(['name' => $name]);
        $user->assignRole($role);

        return $user;
    }

    public function test_alerts_are_limited_to_modules_the_user_may_list(): void
    {
        Task::factory()->create(['status' => TaskStatus::Todo, 'due_at' => now()->subDay()]);

        $this->assertEmpty(AiAssistant::visibleAlerts($this->userWith([])));
        $this->assertNotEmpty(AiAssistant::visibleAlerts($this->userWith(['tasks.view_any'])));
    }

    public function test_the_morning_digest_only_reaches_people_who_may_see_the_alert(): void
    {
        Notification::fake();
        $guest = Guest::factory()->create();
        GuestTicket::create(['guest_id' => $guest->id, 'subject' => 'اینترنت قطع است', 'status' => TicketStatus::Open, 'sla_due_at' => now()->subHours(5)]);

        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));
        $withModule = $this->userWith(['guest-tickets.view_any']);
        $withoutModule = $this->userWith([]);

        app(AssistantAlertDigestService::class)->notifyDailyDigest();

        Notification::assertSentTo($admin, AssistantAlertsDigestNotification::class);
        Notification::assertSentTo($withModule, AssistantAlertsDigestNotification::class);
        Notification::assertNotSentTo($withoutModule, AssistantAlertsDigestNotification::class);
    }

    public function test_a_refused_tool_call_is_logged_as_denied(): void
    {
        AiProvider::factory()->active()->create();
        Http::fakeSequence()
            ->push(['choices' => [['message' => [
                'role' => 'assistant',
                'content' => null,
                'tool_calls' => [['id' => 'c1', 'type' => 'function', 'function' => ['name' => 'get_attendance_summary', 'arguments' => '{}']]],
            ]]]])
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'دسترسی ندارید.']]]]);
        $this->actingAs($this->userWith([]));

        Livewire::test(AiAssistant::class)->set('body', 'وضعیت حضور؟')->call('send');

        $entry = Activity::where('log_name', 'ai_assistant')->sole();
        $this->assertSame('tool_denied', $entry->event);
        $this->assertSame('get_attendance_summary', $entry->properties['tool']);
    }

    public function test_the_usage_dashboard_lists_recent_tool_calls_with_denials(): void
    {
        $admin = User::factory()->create(['name' => 'مدیر سیستم']);
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));
        activity('ai_assistant')->causedBy($admin)->event('tool_denied')->withProperties(['tool' => 'get_wallet_activity_summary'])->log('x');

        Livewire::actingAs($admin)
            ->test(AiAssistantUsageDashboard::class)
            ->assertSee('get_wallet_activity_summary')
            ->assertSee('رد شد');
    }

    public function test_the_user_list_is_only_given_to_the_model_when_the_user_may_list_users(): void
    {
        AiProvider::factory()->active()->create();
        Http::fake(['*' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => 'سلام']]]])]);
        User::factory()->create(['name' => 'نام محرمانهٔ همکار']);

        $this->actingAs($this->userWith([]));
        Livewire::test(AiAssistant::class)->set('body', 'سلام')->call('send');

        Http::assertSent(fn (Request $request) => ! str_contains(json_encode($request->data()['messages'], JSON_UNESCAPED_UNICODE), 'نام محرمانهٔ همکار'));

        $this->actingAs($this->userWith(['users.view_any'], 'کاربر با دسترسی'));
        Livewire::test(AiAssistant::class)->set('body', 'سلام دوباره')->call('send');

        Http::assertSent(fn (Request $request) => str_contains(json_encode($request->data()['messages'], JSON_UNESCAPED_UNICODE), 'نام محرمانهٔ همکار'));
    }

    public function test_old_tool_output_is_blanked_but_recent_output_and_answers_stay(): void
    {
        $user = User::factory()->create();
        $conversation = AiConversation::create(['user_id' => $user->id]);
        $old = $conversation->messages()->create(['role' => AiMessageRole::Tool, 'content' => '{"secret":1}', 'tool_call_id' => 'a']);
        $old->forceFill(['created_at' => now()->subDays(120)])->save();
        $recent = $conversation->messages()->create(['role' => AiMessageRole::Tool, 'content' => '{"secret":2}', 'tool_call_id' => 'b']);
        $answer = $conversation->messages()->create(['role' => AiMessageRole::Assistant, 'content' => 'پاسخ قدیمی']);
        $answer->forceFill(['created_at' => now()->subDays(120)])->save();

        $pruned = app(AssistantRetention::class)->pruneToolOutput(90);

        $this->assertSame(1, $pruned);
        $this->assertSame(AssistantRetention::PRUNED_NOTE, $old->fresh()->content);
        $this->assertSame('{"secret":2}', $recent->fresh()->content);
        $this->assertSame('پاسخ قدیمی', $answer->fresh()->content);
    }

    public function test_a_retention_of_zero_days_keeps_everything(): void
    {
        $user = User::factory()->create();
        $message = AiConversation::create(['user_id' => $user->id])->messages()->create(['role' => AiMessageRole::Tool, 'content' => '{"x":1}', 'tool_call_id' => 'a']);
        $message->forceFill(['created_at' => now()->subYears(2)])->save();

        $this->assertSame(0, app(AssistantRetention::class)->pruneToolOutput(0));
        $this->assertSame('{"x":1}', $message->fresh()->content);
    }
}
