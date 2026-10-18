<?php

namespace Tests\Feature;

use App\Domain\Ai\AiToolAccess;
use App\Domain\Ai\ScheduledReportRunner;
use App\Enums\AiMessageRole;
use App\Enums\BookingStatus;
use App\Exceptions\AiProviderException;
use App\Filament\Pages\AiAssistant;
use App\Filament\Resources\AiScheduledReports\Pages\ManageAiScheduledReports;
use App\Jobs\RunAiScheduledReportJob;
use App\Models\AiConversation;
use App\Models\AiProvider;
use App\Models\AiScheduledReport;
use App\Models\Booking;
use App\Models\CashierShift;
use App\Models\Guest;
use App\Models\Order;
use App\Models\Task;
use App\Models\User;
use App\Models\WalletTopup;
use App\Notifications\AssistantAlertsDigestNotification;
use App\Notifications\ScheduledReportReadyNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AiAssistantScheduledReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWith(array $permissions = []): User
    {
        $this->artisan('access-control:sync');

        $role = Role::create(['name' => 'نقش '.uniqid()]);
        $role->givePermissionTo(Permission::whereIn('name', ['ai-assistant.access', 'ai-assistant.report', ...$permissions])->get());

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    private function toolCall(string $name, array $arguments = []): array
    {
        return ['choices' => [['message' => [
            'role' => 'assistant',
            'content' => null,
            'tool_calls' => [['id' => 'c1', 'type' => 'function', 'function' => ['name' => $name, 'arguments' => json_encode($arguments)]]],
        ]]]];
    }

    private function answer(string $content): array
    {
        return ['choices' => [['message' => ['role' => 'assistant', 'content' => $content]]]];
    }

    public function test_a_report_runs_as_its_owner_and_leaves_a_conversation_and_a_notification(): void
    {
        Notification::fake();
        AiProvider::factory()->active()->create();
        Http::fakeSequence()->push($this->toolCall('get_task_summary'))->push($this->answer('همه چیز مرتب است.'));
        $owner = $this->userWith(['tasks.view_any']);
        $report = AiScheduledReport::factory()->create(['user_id' => $owner->id, 'prompt' => 'وضعیت وظایف؟']);

        $conversation = app(ScheduledReportRunner::class)->run($report);

        $this->assertNotNull($conversation);
        $this->assertSame($owner->id, $conversation->user_id);
        $this->assertSame(['وضعیت وظایف؟', 'همه چیز مرتب است.'], $conversation->messages->pluck('content')->all());
        $this->assertSame(AiMessageRole::Assistant, $conversation->messages->last()->role);
        $this->assertNotNull($report->fresh()->last_run_at);
        $this->assertSame($conversation->id, $report->fresh()->last_conversation_id);
        Notification::assertSentTo($owner, ScheduledReportReadyNotification::class);
    }

    public function test_the_owners_module_permissions_apply_to_the_report(): void
    {
        Notification::fake();
        AiProvider::factory()->active()->create();
        Http::fakeSequence()->push($this->toolCall('get_attendance_summary'))->push($this->answer('دسترسی ندارم.'));
        $owner = $this->userWith();
        $report = AiScheduledReport::factory()->create(['user_id' => $owner->id]);

        app(ScheduledReportRunner::class)->run($report);

        $this->assertSame('tool_denied', Activity::where('log_name', 'ai_assistant')->sole()->event);
    }

    public function test_an_unattended_report_cannot_create_anything(): void
    {
        Notification::fake();
        AiProvider::factory()->active()->create();
        Http::fakeSequence()->push($this->toolCall('create_task', ['title' => 'نباید ساخته شود']))->push($this->answer('انجام نشد.'));
        $owner = $this->userWith(['ai-assistant.act', 'tasks.create']);
        $report = AiScheduledReport::factory()->create(['user_id' => $owner->id]);

        app(ScheduledReportRunner::class)->run($report);

        $this->assertSame(0, Task::count());
        $this->assertNull(AiConversation::query()->whereNotNull('pending_actions')->first());
    }

    public function test_an_owner_without_report_access_gets_nothing_and_the_model_is_not_called(): void
    {
        Notification::fake();
        AiProvider::factory()->active()->create();
        Http::fake();
        $this->artisan('access-control:sync');
        $role = Role::create(['name' => 'بدون گزارش']);
        $role->givePermissionTo(Permission::where('name', 'tasks.view_any')->get());
        $owner = User::factory()->create();
        $owner->assignRole($role);
        $report = AiScheduledReport::factory()->create(['user_id' => $owner->id]);

        $this->assertNull(app(ScheduledReportRunner::class)->run($report));

        Http::assertNothingSent();
        $this->assertNotNull($report->fresh()->last_run_at);
        Notification::assertNothingSent();
    }

    public function test_without_a_provider_the_report_is_skipped_quietly(): void
    {
        Notification::fake();
        $report = AiScheduledReport::factory()->create(['user_id' => $this->userWith()->id]);

        $this->assertNull(app(ScheduledReportRunner::class)->run($report));
        $this->assertSame(0, AiConversation::count());
    }

    public function test_a_provider_failure_keeps_the_report_due_and_never_breaks_the_job(): void
    {
        Sleep::fake();
        Notification::fake();
        AiProvider::factory()->active()->create();
        Http::fake(['*' => Http::response(['error' => ['message' => 'down']], 503)]);
        $report = AiScheduledReport::factory()->create(['user_id' => $this->userWith()->id]);

        try {
            app(ScheduledReportRunner::class)->run($report);
            $this->fail('Expected an AiProviderException.');
        } catch (AiProviderException) {
            $this->assertNull($report->fresh()->last_run_at);
        }

        (new RunAiScheduledReportJob($report->id))->handle(app(ScheduledReportRunner::class));

        $this->assertNull($report->fresh()->last_run_at);
    }

    public function test_the_command_dispatches_only_reports_that_are_due_and_not_yet_run_today(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-10-20 09:00', 'Asia/Tehran'));
        $user = $this->userWith();
        $due = AiScheduledReport::factory()->create(['user_id' => $user->id, 'run_at_time' => '08:00']);
        AiScheduledReport::factory()->create(['user_id' => $user->id, 'run_at_time' => '23:00']);
        AiScheduledReport::factory()->create(['user_id' => $user->id, 'run_at_time' => '08:00', 'last_run_at' => now()]);
        AiScheduledReport::factory()->create(['user_id' => $user->id, 'run_at_time' => '08:00', 'is_active' => false]);

        Artisan::call('ai-assistant:run-scheduled-reports');

        Queue::assertPushed(RunAiScheduledReportJob::class, 1);
        Queue::assertPushed(RunAiScheduledReportJob::class, fn (RunAiScheduledReportJob $job) => $job->reportId === $due->id);
    }

    public function test_the_resource_lists_only_my_reports_and_creates_them_for_me(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $mine = AiScheduledReport::factory()->create(['user_id' => $admin->id]);
        $theirs = AiScheduledReport::factory()->create();

        Livewire::actingAs($admin)->test(ManageAiScheduledReports::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs])
            ->callAction('create', ['prompt' => 'خلاصهٔ فروش بلیت دیروز', 'run_at_time' => '07:30', 'is_active' => true])
            ->assertHasNoActionErrors()
            ->callTableAction('runNow', $mine);

        $this->assertDatabaseHas('ai_scheduled_reports', ['user_id' => $admin->id, 'prompt' => 'خلاصهٔ فروش بلیت دیروز', 'run_at_time' => '07:30']);
        Queue::assertPushed(RunAiScheduledReportJob::class, fn (RunAiScheduledReportJob $job) => $job->reportId === $mine->id);
    }

    public function test_an_invalid_time_is_rejected(): void
    {
        Livewire::actingAs($this->admin())->test(ManageAiScheduledReports::class)
            ->callAction('create', ['prompt' => 'سؤال', 'run_at_time' => '25:99'])
            ->assertHasActionErrors(['run_at_time']);
    }

    public function test_the_morning_alert_digest_is_also_kept_in_the_notifications_table(): void
    {
        $notification = new AssistantAlertsDigestNotification(['پروژه‌ای بیش از بودجه است.']);

        $this->assertContains('database', $notification->via(User::factory()->create()));
        $this->assertSame(['پروژه‌ای بیش از بودجه است.'], $notification->toDatabase(User::factory()->create())['messages']);
    }

    private function runTool(string $tool, array $arguments, array $permissions): array
    {
        AiProvider::factory()->active()->create();
        Http::fakeSequence()->push($this->toolCall($tool, $arguments))->push($this->answer('تمام.'));
        $this->actingAs($this->userWith($permissions));

        Livewire::test(AiAssistant::class)->set('body', 'سؤال')->call('send');

        return json_decode(AiConversation::sole()->messages->firstWhere('role', AiMessageRole::Tool)->content, true);
    }

    public function test_orders_can_be_found_by_phone_with_a_link(): void
    {
        $order = Order::factory()->create(['customer_phone' => '09121234567']);
        Order::factory()->create(['customer_phone' => '09350000000']);

        $payload = $this->runTool('search_orders', ['customer_phone' => '0912123'], ['orders.view_any']);

        $this->assertSame(1, $payload['count']);
        $this->assertSame($order->order_number, $payload['orders'][0]['order_number']);
        $this->assertStringContainsString("/orders/{$order->id}", $payload['orders'][0]['link']);
    }

    public function test_cashier_shifts_report_differences(): void
    {
        $cashier = User::factory()->create(['name' => 'صندوقدار شب']);
        CashierShift::create(['user_id' => $cashier->id, 'opening_float' => 0, 'opened_at' => now()->subHours(9), 'closed_at' => now()->subHour(), 'expected_cash' => 500000, 'counted_cash' => 450000, 'difference' => -50000]);
        CashierShift::create(['user_id' => $cashier->id, 'opening_float' => 0, 'opened_at' => now()->subMinutes(30)]);

        $payload = $this->runTool('get_cashier_shifts', [], ['orders.view_any']);

        $this->assertCount(2, $payload['shifts']);
        $this->assertTrue($payload['shifts'][0]['is_open']);
        $this->assertSame(-50000, $payload['shifts'][1]['difference_toman']);
        $this->assertSame(1, $payload['shifts_with_difference']);
    }

    public function test_wallet_liability_adds_up_the_balances(): void
    {
        Guest::factory()->create(['name' => 'بزرگ', 'wallet_balance' => 100000]);
        Guest::factory()->create(['wallet_balance' => 50000]);
        Guest::factory()->create(['wallet_balance' => 0]);
        WalletTopup::create(['guest_id' => Guest::factory()->create()->id, 'amount' => 200000, 'authority' => 'A00000000000000000000000000000001']);

        $payload = $this->runTool('get_wallet_liability', [], ['guests.view_any']);

        $this->assertSame(150000, $payload['total_liability_toman']);
        $this->assertSame(2, $payload['customers_with_balance']);
        $this->assertSame('بزرگ', $payload['largest_balances'][0]['name']);
        $this->assertSame(1, $payload['pending_online_topups']);
    }

    public function test_guest_filters_combine_across_modules(): void
    {
        $both = Guest::factory()->create(['name' => 'هر دو', 'wallet_balance' => 80000]);
        $onlyCancelled = Guest::factory()->create(['name' => 'فقط لغو', 'wallet_balance' => 0]);
        Guest::factory()->create(['name' => 'فقط کیف پول', 'wallet_balance' => 90000]);
        Booking::factory()->create(['guest_id' => $both->id, 'status' => BookingStatus::Cancelled]);
        Booking::factory()->create(['guest_id' => $onlyCancelled->id, 'status' => BookingStatus::Cancelled]);

        $payload = $this->runTool('search_guests', ['has_cancelled_booking' => true, 'min_wallet_balance' => 50000], ['guests.view_any']);

        $this->assertSame(['هر دو'], array_column($payload['guests'], 'name'));
    }

    public function test_the_new_tools_need_their_module_permission(): void
    {
        $user = $this->userWith();

        $this->assertFalse(AiToolAccess::allowsTool($user, 'search_orders'));
        $this->assertFalse(AiToolAccess::allowsTool($user, 'get_cashier_shifts'));
        $this->assertFalse(AiToolAccess::allowsTool($user, 'get_wallet_liability'));
    }
}
