<?php

namespace Tests\Feature;

use App\Enums\AiMessageRole;
use App\Enums\BookingStatus;
use App\Enums\DealSource;
use App\Enums\DealStage;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\WalletTransactionType;
use App\Filament\Pages\AiAssistant;
use App\Models\AiConversation;
use App\Models\AiProvider;
use App\Models\Booking;
use App\Models\Deal;
use App\Models\Guest;
use App\Models\GuestFeedback;
use App\Models\GuestTicket;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Covers the eight CRM/customer reporting tools added on top of the
 * pre-existing get_crm_summary: a per-customer 360 view, customer search,
 * named breached support tickets, sales-rep pipeline performance, deal
 * source breakdown, top customers by lifetime value, NPS satisfaction, and
 * wallet activity.
 */
class AiAssistantCrmGuestToolsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsFullyPermittedUser(): User
    {
        $this->artisan('access-control:sync');

        $role = Role::create(['name' => 'مدیر کامل CRM']);
        $role->givePermissionTo(Permission::whereIn('name', [
            'ai-assistant.access', 'ai-assistant.report',
        ])->orWhere('name', 'like', '%.view_any')->orWhere('name', 'tasks.create')->get());

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

    private function createCompletedBooking(Guest $guest, int $basePrice = 1_000_000): Booking
    {
        $roomType = RoomType::factory()->create(['base_price' => $basePrice]);
        $room = Room::factory()->create(['room_type_id' => $roomType->id]);

        return Booking::factory()->create([
            'guest_id' => $guest->id,
            'room_id' => $room->id,
            'status' => BookingStatus::CheckedOut,
            'check_in_date' => today()->subDays(2),
            'check_out_date' => today()->subDay(),
            'actual_check_out_at' => now(),
        ]);
    }

    public function test_guest_detail_reports_loyalty_wallet_and_open_items(): void
    {
        AiProvider::factory()->active()->create();
        $guest = Guest::factory()->create(['name' => 'رضا کریمی', 'wallet_balance' => 500_000]);
        $this->createCompletedBooking($guest, 1_000_000);

        Deal::create(['guest_id' => $guest->id, 'title' => 'بسته آپگرید اتاق', 'stage' => DealStage::NewLead, 'value' => 2_000_000]);
        GuestTicket::create(['guest_id' => $guest->id, 'subject' => 'درخواست تمیزکاری', 'status' => TicketStatus::Open, 'priority' => TicketPriority::Normal]);
        GuestFeedback::create(['guest_id' => $guest->id, 'nps_score' => 9, 'comment' => 'عالی بود', 'submitted_at' => now()]);

        $this->fakeToolCallThenAnswer('get_guest_detail', ['guest_name' => 'رضا کریمی']);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'مشتری رضا کریمی چه وضعیتی داره؟')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertSame('رضا کریمی', $payload['name']);
        $this->assertSame('نقره‌ای', $payload['loyalty_tier']);
        $this->assertSame(500_000, $payload['wallet_balance_toman']);
        $this->assertSame(1, $payload['completed_stays']);
        $this->assertSame(1_000_000, $payload['lifetime_value_toman']);
        $this->assertSame(1, $payload['open_deals']);
        $this->assertSame(1, $payload['open_support_tickets']);
        $this->assertSame(9, $payload['latest_feedback_score']);
    }

    public function test_search_guests_filters_by_loyalty_tier(): void
    {
        AiProvider::factory()->active()->create();
        Guest::factory()->create(['name' => 'مشتری بدون سابقه']); // stays Bronze
        $returning = Guest::factory()->create(['name' => 'مشتری بازگشتی']);
        $this->createCompletedBooking($returning);

        $this->fakeToolCallThenAnswer('search_guests', ['loyalty_tier' => 'silver']);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'مشتریان سطح نقره‌ای رو نشون بده')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertSame(1, $payload['count']);
        $this->assertSame('مشتری بازگشتی', $payload['guests'][0]['name']);
    }

    public function test_breached_support_tickets_names_the_customer_and_subject(): void
    {
        AiProvider::factory()->active()->create();
        $guest = Guest::factory()->create(['name' => 'سارا احمدی']);
        GuestTicket::create([
            'guest_id' => $guest->id, 'subject' => 'اینترنت اتاق قطع است',
            'status' => TicketStatus::Open, 'sla_due_at' => now()->subHours(5),
        ]);
        // Not yet breached - must not appear.
        GuestTicket::create([
            'guest_id' => $guest->id, 'subject' => 'درخواست ملحفه اضافه',
            'status' => TicketStatus::Open, 'sla_due_at' => now()->addHours(10),
        ]);

        $this->fakeToolCallThenAnswer('get_breached_support_tickets', []);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'کدام تیکت پشتیبانی از مهلتش گذشته؟')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertSame(1, $payload['breached_count']);
        $this->assertSame('اینترنت اتاق قطع است', $payload['tickets'][0]['subject']);
        $this->assertSame('سارا احمدی', $payload['tickets'][0]['guest']);
        $this->assertSame(5, $payload['tickets'][0]['overdue_hours']);
    }

    public function test_deals_by_assignee_reports_open_pipeline_and_win_rate(): void
    {
        AiProvider::factory()->active()->create();
        $rep = User::factory()->create(['name' => 'کارشناس فروش']);
        Deal::create(['title' => 'معاملهٔ باز', 'stage' => DealStage::NewLead, 'value' => 2_000_000, 'assigned_to' => $rep->id]);
        Deal::create(['title' => 'معاملهٔ برده', 'stage' => DealStage::Won, 'value' => 5_000_000, 'assigned_to' => $rep->id]);
        Deal::create(['title' => 'معاملهٔ ازدست‌رفته', 'stage' => DealStage::Lost, 'value' => 1_000_000, 'assigned_to' => $rep->id]);

        $this->fakeToolCallThenAnswer('get_deals_by_assignee', []);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'عملکرد فروش هر کارشناس چطوره؟')
            ->call('send');

        $payload = $this->toolResultPayload();
        $row = $payload['assignees'][0];

        $this->assertSame('کارشناس فروش', $row['user']);
        $this->assertSame(1, $row['open_deals']);
        $this->assertSame(2_000_000, $row['open_pipeline_value_toman']);
        $this->assertSame(1, $row['won_count']);
        $this->assertEquals(50.0, $row['win_rate_percent']);
    }

    public function test_deal_source_breakdown_separates_referral_from_walk_in(): void
    {
        AiProvider::factory()->active()->create();
        Deal::create(['title' => 'معرفی دوست', 'stage' => DealStage::Won, 'value' => 3_000_000, 'source' => DealSource::Referral]);
        Deal::create(['title' => 'مراجعه حضوری', 'stage' => DealStage::NewLead, 'value' => 1_000_000, 'source' => DealSource::WalkIn]);

        $this->fakeToolCallThenAnswer('get_deal_source_breakdown', ['period' => 'this_month']);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'مشتری‌ها بیشتر از کجا میان؟')
            ->call('send');

        $payload = $this->toolResultPayload();
        $referral = collect($payload['sources'])->firstWhere('source', DealSource::Referral->getLabel());

        $this->assertSame(1, $referral['deals']);
        $this->assertSame(3_000_000, $referral['value_toman']);
        $this->assertEquals(100.0, $referral['win_rate_percent']);
    }

    public function test_top_guests_by_value_ranks_the_higher_spender_first(): void
    {
        AiProvider::factory()->active()->create();
        $lowSpender = Guest::factory()->create(['name' => 'مشتری معمولی']);
        $this->createCompletedBooking($lowSpender, 1_000_000);

        $highSpender = Guest::factory()->create(['name' => 'مشتری ویژه']);
        $this->createCompletedBooking($highSpender, 5_000_000);

        $this->fakeToolCallThenAnswer('get_top_guests_by_value', ['limit' => 10]);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'مشتریان برتر رو نشون بده')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertSame('مشتری ویژه', $payload['guests'][0]['name']);
        $this->assertSame(5_000_000, $payload['guests'][0]['lifetime_value_toman']);
    }

    public function test_guest_satisfaction_summary_computes_the_nps_score(): void
    {
        AiProvider::factory()->active()->create();
        $guest = Guest::factory()->create();
        GuestFeedback::create(['guest_id' => $guest->id, 'nps_score' => 10, 'submitted_at' => now()]);
        GuestFeedback::create(['guest_id' => $guest->id, 'nps_score' => 8, 'submitted_at' => now()]);
        GuestFeedback::create(['guest_id' => $guest->id, 'nps_score' => 3, 'submitted_at' => now()]);

        $this->fakeToolCallThenAnswer('get_guest_satisfaction_summary', ['period' => 'this_month']);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'رضایت مشتری‌ها این ماه چطوره؟')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertSame(3, $payload['responses']);
        $this->assertSame(1, $payload['promoters']);
        $this->assertSame(1, $payload['passives']);
        $this->assertSame(1, $payload['detractors']);
        $this->assertEqualsWithDelta(7.0, $payload['average_score'], 0.01);
        $this->assertEqualsWithDelta(0.0, $payload['nps_score'], 0.01);
    }

    public function test_wallet_activity_summary_separates_credited_from_debited(): void
    {
        AiProvider::factory()->active()->create();
        $guest = Guest::factory()->create();
        WalletTransaction::create(['guest_id' => $guest->id, 'type' => WalletTransactionType::Credit, 'amount' => 200_000, 'reason' => 'کش‌بک رزرو']);
        WalletTransaction::create(['guest_id' => $guest->id, 'type' => WalletTransactionType::Debit, 'amount' => 50_000, 'reason' => 'مصرف در رزرو بعدی']);

        $this->fakeToolCallThenAnswer('get_wallet_activity_summary', ['period' => 'this_month']);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'وضعیت کیف پول مشتری‌ها این ماه چطوره؟')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertSame(200_000, $payload['credited_toman']);
        $this->assertSame(50_000, $payload['debited_toman']);
        $this->assertSame(150_000, $payload['net_toman']);
    }

    public function test_a_user_without_the_report_permission_cannot_use_a_crm_tool(): void
    {
        AiProvider::factory()->active()->create();
        Guest::factory()->create();

        $this->fakeToolCallThenAnswer('search_guests', []);

        // A roleless user keeps unrestricted access under this app's
        // Gate::before bypass (AppServiceProvider::registerAccessControl),
        // so the restriction only takes effect once a role is assigned.
        $this->artisan('access-control:sync');
        $role = Role::create(['name' => 'مشاهده‌گر CRM']);
        $role->givePermissionTo(Permission::where('name', 'ai-assistant.access')->sole());
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        Livewire::test(AiAssistant::class)
            ->set('body', 'مشتری‌ها رو نشون بده')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertArrayHasKey('error', $payload);
    }
}
