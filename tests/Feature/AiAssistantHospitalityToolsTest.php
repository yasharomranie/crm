<?php

namespace Tests\Feature;

use App\Enums\AiMessageRole;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Filament\Pages\AiAssistant;
use App\Models\AiConversation;
use App\Models\AiProvider;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Covers the eight hospitality reporting tools added on top of the
 * pre-existing get_accommodation_summary: occupancy forecast, booking
 * search, upcoming arrivals/departures, overdue checkouts, revenue by room
 * type, booking source breakdown, and cancellation rate.
 */
class AiAssistantHospitalityToolsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsFullyPermittedUser(): User
    {
        $this->artisan('access-control:sync');

        $role = Role::create(['name' => 'مدیر کامل هتل‌داری']);
        $role->givePermissionTo(Permission::whereIn('name', [
            'ai-assistant.access', 'ai-assistant.report',
        ])->get());

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

    public function test_occupancy_forecast_reports_todays_occupied_room_and_its_arrival(): void
    {
        AiProvider::factory()->active()->create();
        Room::factory()->create(['is_active' => true]); // a second, empty room
        $booking = Booking::factory()->create([
            'status' => BookingStatus::Confirmed,
            'check_in_date' => today(),
            'check_out_date' => today()->addDays(2),
        ]);

        $this->fakeToolCallThenAnswer('get_occupancy_forecast', ['days' => 1]);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'امروز چقدر اتاق خالی داریم؟')
            ->call('send');

        $payload = $this->toolResultPayload();
        $today = $payload['forecast'][0];

        $this->assertSame(2, $payload['total_active_rooms']);
        $this->assertSame(1, $today['occupied']);
        $this->assertSame(1, $today['available']);
        $this->assertSame(1, $today['arrivals']);
        $this->assertSame($booking->check_in_date->toDateString(), $today['date']);
    }

    public function test_search_bookings_finds_a_booking_by_partial_guest_name(): void
    {
        AiProvider::factory()->active()->create();
        $guest = Guest::factory()->create(['name' => 'رضا کریمی']);
        $booking = Booking::factory()->create(['guest_id' => $guest->id]);
        Booking::factory()->create(); // an unrelated booking that must not match

        $this->fakeToolCallThenAnswer('search_bookings', ['guest_name' => 'کریمی']);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'رزرو آقای کریمی رو پیدا کن')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertSame(1, $payload['count']);
        $this->assertSame($booking->reference_code, $payload['bookings'][0]['reference_code']);
        $this->assertSame('رضا کریمی', $payload['bookings'][0]['guest_name']);
    }

    public function test_upcoming_arrivals_lists_todays_named_guest_not_just_a_count(): void
    {
        AiProvider::factory()->active()->create();
        $guest = Guest::factory()->create(['name' => 'سارا احمدی']);
        Booking::factory()->create([
            'guest_id' => $guest->id,
            'status' => BookingStatus::Confirmed,
            'check_in_date' => today(),
            'check_out_date' => today()->addDays(1),
        ]);

        $this->fakeToolCallThenAnswer('get_upcoming_arrivals', ['days' => 1]);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'امروز کیا میان؟')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertSame(1, $payload['arrivals_count']);
        $this->assertSame('سارا احمدی', $payload['arrivals'][0]['guest_name']);
    }

    public function test_upcoming_departures_lists_the_in_house_guest_checking_out_today(): void
    {
        AiProvider::factory()->active()->create();
        $guest = Guest::factory()->create(['name' => 'علی رضایی']);
        Booking::factory()->create([
            'guest_id' => $guest->id,
            'status' => BookingStatus::CheckedIn,
            'check_in_date' => today()->subDays(2),
            'check_out_date' => today(),
        ]);

        $this->fakeToolCallThenAnswer('get_upcoming_departures', ['days' => 1]);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'امروز کیا میرن؟')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertSame(1, $payload['departures_count']);
        $this->assertSame('علی رضایی', $payload['departures'][0]['guest_name']);
    }

    public function test_overdue_checkouts_flags_a_guest_still_in_house_past_their_checkout_date(): void
    {
        AiProvider::factory()->active()->create();
        $guest = Guest::factory()->create(['name' => 'مریم نوری']);
        Booking::factory()->create([
            'guest_id' => $guest->id,
            'status' => BookingStatus::CheckedIn,
            'check_in_date' => today()->subDays(5),
            'check_out_date' => today()->subDays(2),
        ]);
        // A guest still correctly in-house with a future checkout must not appear.
        Booking::factory()->create([
            'status' => BookingStatus::CheckedIn,
            'check_in_date' => today(),
            'check_out_date' => today()->addDays(2),
        ]);

        $this->fakeToolCallThenAnswer('get_overdue_checkouts', []);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'کدام مهمان از تاریخ خروجش گذشته؟')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertSame(1, $payload['overdue_count']);
        $this->assertSame('مریم نوری', $payload['overdue_checkouts'][0]['guest_name']);
        $this->assertSame(2, $payload['overdue_checkouts'][0]['days_overdue']);
    }

    public function test_revenue_by_room_type_computes_adr_from_a_checked_out_booking(): void
    {
        AiProvider::factory()->active()->create();
        $roomType = RoomType::factory()->create(['name' => 'ویلای دوخوابه', 'base_price' => 1_000_000]);
        $room = Room::factory()->create(['room_type_id' => $roomType->id]);

        Booking::factory()->create([
            'room_id' => $room->id,
            'status' => BookingStatus::CheckedOut,
            'check_in_date' => today()->subDays(2),
            'check_out_date' => today(),
            'actual_check_out_at' => now(),
        ]);

        $this->fakeToolCallThenAnswer('get_revenue_by_room_type', ['period' => 'this_month']);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'درآمد این ماه به تفکیک نوع اتاق چقدره؟')
            ->call('send');

        $payload = $this->toolResultPayload();
        $row = collect($payload['room_types'])->firstWhere('room_type', 'ویلای دوخوابه');

        $this->assertNotNull($row);
        $this->assertSame(1, $row['bookings_checked_out']);
        $this->assertSame(2, $row['nights_sold']);
        $this->assertSame(2_000_000, $row['revenue_toman']);
        $this->assertSame(1_000_000, $row['adr_toman']);
    }

    public function test_booking_source_breakdown_separates_ota_from_walk_in_revenue(): void
    {
        AiProvider::factory()->active()->create();
        Booking::factory()->create([
            'source' => BookingSource::Ota,
            'status' => BookingStatus::CheckedOut,
            'check_in_date' => today()->subDays(1),
            'check_out_date' => today(),
            'actual_check_out_at' => now(),
            'commission_amount' => 150_000,
        ]);

        $this->fakeToolCallThenAnswer('get_booking_source_breakdown', ['period' => 'this_month']);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'چقدر از رزروها از سایت واسط اومده؟')
            ->call('send');

        $payload = $this->toolResultPayload();
        $otaRow = collect($payload['sources'])->firstWhere('source', BookingSource::Ota->getLabel());

        $this->assertNotNull($otaRow);
        $this->assertSame(1, $otaRow['bookings']);
        $this->assertSame(150_000, $otaRow['commission_owed_toman']);
    }

    public function test_cancellation_summary_computes_the_rate_within_the_created_cohort(): void
    {
        AiProvider::factory()->active()->create();
        Booking::factory()->create(['status' => BookingStatus::Confirmed]);
        $cancelled = Booking::factory()->create(['status' => BookingStatus::Confirmed]);
        $cancelled->update(['status' => BookingStatus::Cancelled]);

        $this->fakeToolCallThenAnswer('get_cancellation_summary', ['period' => 'this_month']);
        $this->actingAsFullyPermittedUser();

        Livewire::test(AiAssistant::class)
            ->set('body', 'نرخ کنسلی رزروهای این ماه چقدره؟')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertSame(2, $payload['bookings_created']);
        $this->assertSame(1, $payload['cancelled_count']);
        $this->assertEquals(50.0, $payload['cancellation_rate_percent']);
    }

    public function test_a_user_without_the_report_permission_cannot_use_a_hospitality_tool(): void
    {
        AiProvider::factory()->active()->create();
        Booking::factory()->create();

        $this->fakeToolCallThenAnswer('get_occupancy_forecast', []);

        // A roleless user keeps unrestricted access under this app's
        // Gate::before bypass (AppServiceProvider::registerAccessControl),
        // so the restriction only takes effect once a role is assigned.
        $this->artisan('access-control:sync');
        $role = Role::create(['name' => 'مشاهده‌گر هتل‌داری']);
        $role->givePermissionTo(Permission::where('name', 'ai-assistant.access')->sole());
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        Livewire::test(AiAssistant::class)
            ->set('body', 'امروز چقدر اتاق خالی داریم؟')
            ->call('send');

        $payload = $this->toolResultPayload();

        $this->assertArrayHasKey('error', $payload);
    }
}
