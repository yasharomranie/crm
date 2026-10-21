<?php

namespace Tests\Feature;

use App\Domain\Ai\CrmToolCatalog;
use App\Enums\BookingStatus;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\AiAssistantProfile;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\GuestTicket;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The curated, read-only CRM tool catalog exposed to custom AI assistant
 * profiles (HasCustomAssistantChatEngine, gated by
 * AiAssistantProfile::$allowed_tools + "ai-assistant.report") - tested
 * directly here since the catalog's own query logic is independent of the
 * chat/tool-call plumbing around it (covered separately in
 * AiAssistantsHubTest).
 */
class CrmToolCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_room_availability_excludes_rooms_with_an_overlapping_booking(): void
    {
        $roomType = RoomType::factory()->create(['name' => 'ویلای استخردار', 'base_price' => 3_000_000]);
        $freeRoom = Room::factory()->create(['room_type_id' => $roomType->id, 'is_active' => true]);
        $bookedRoom = Room::factory()->create(['room_type_id' => $roomType->id, 'is_active' => true]);

        Booking::factory()->create([
            'room_id' => $bookedRoom->id,
            'status' => BookingStatus::Confirmed,
            'check_in_date' => today()->addDays(5),
            'check_out_date' => today()->addDays(8),
        ]);

        $result = app(CrmToolCatalog::class)->execute('check_room_availability', [
            'check_in' => today()->addDays(5)->toDateString(),
            'check_out' => today()->addDays(7)->toDateString(),
        ]);

        $this->assertSame(1, $result['available_rooms_count']);
        $this->assertSame($freeRoom->name, $result['rooms'][0]['name']);
    }

    public function test_check_room_availability_ignores_a_cancelled_overlapping_booking(): void
    {
        $roomType = RoomType::factory()->create(['base_price' => 1_000_000]);
        $room = Room::factory()->create(['room_type_id' => $roomType->id, 'is_active' => true]);

        Booking::factory()->create([
            'room_id' => $room->id,
            'status' => BookingStatus::Cancelled,
            'check_in_date' => today()->addDays(2),
            'check_out_date' => today()->addDays(4),
        ]);

        $result = app(CrmToolCatalog::class)->execute('check_room_availability', [
            'check_in' => today()->addDays(2)->toDateString(),
            'check_out' => today()->addDays(4)->toDateString(),
        ]);

        $this->assertSame(1, $result['available_rooms_count']);
    }

    public function test_check_room_availability_rejects_an_invalid_date_range(): void
    {
        $result = app(CrmToolCatalog::class)->execute('check_room_availability', [
            'check_in' => today()->addDays(5)->toDateString(),
            'check_out' => today()->toDateString(),
        ]);

        $this->assertArrayHasKey('error', $result);
    }

    public function test_lookup_guest_finds_by_mobile_and_reports_open_items(): void
    {
        $guest = Guest::factory()->create(['name' => 'رضا کریمی', 'mobile' => '09120000000']);
        GuestTicket::create(['guest_id' => $guest->id, 'subject' => 'سؤال', 'status' => TicketStatus::Open, 'priority' => TicketPriority::Normal]);

        $result = app(CrmToolCatalog::class)->execute('lookup_guest', ['name_or_mobile' => '09120000000']);

        $this->assertSame('رضا کریمی', $result['name']);
        $this->assertSame(1, $result['open_support_tickets']);
    }

    public function test_lookup_guest_returns_an_error_when_nothing_matches(): void
    {
        $result = app(CrmToolCatalog::class)->execute('lookup_guest', ['name_or_mobile' => 'شخص ناشناس']);

        $this->assertArrayHasKey('error', $result);
    }

    public function test_check_support_ticket_status_excludes_resolved_tickets(): void
    {
        $guest = Guest::factory()->create(['name' => 'سارا احمدی']);
        GuestTicket::create(['guest_id' => $guest->id, 'subject' => 'باز', 'status' => TicketStatus::Open, 'priority' => TicketPriority::Normal]);
        GuestTicket::create(['guest_id' => $guest->id, 'subject' => 'حل‌شده', 'status' => TicketStatus::Resolved, 'priority' => TicketPriority::Normal]);

        $result = app(CrmToolCatalog::class)->execute('check_support_ticket_status', ['guest_name' => 'سارا احمدی']);

        $this->assertSame(1, $result['open_count']);
        $this->assertSame('باز', $result['tickets'][0]['subject']);
    }

    public function test_an_unknown_tool_name_returns_an_error(): void
    {
        $result = app(CrmToolCatalog::class)->execute('delete_everything', []);

        $this->assertArrayHasKey('error', $result);
    }

    public function test_the_hotel_report_tool_summarises_the_period_and_names_the_top_channels(): void
    {
        $room = Room::factory()->create(['is_active' => true]);
        Booking::factory()->create([
            'room_id' => $room->id,
            'status' => BookingStatus::Confirmed,
            'check_in_date' => today()->subDays(2),
            'check_out_date' => today()->addDay(),
        ]);

        $week = app(CrmToolCatalog::class)->execute('get_hotel_report', ['period' => 'week']);
        $month = app(CrmToolCatalog::class)->execute('get_hotel_report', []);

        $this->assertSame(today()->subDays(6)->toDateString(), $week['from']);
        $this->assertSame(today()->subDays(29)->toDateString(), $month['from']);
        $this->assertSame(today()->toDateString(), $week['to']);
        $this->assertSame(1, $week['bookings']);
        $this->assertGreaterThan(0, $week['room_nights_sold']);
        $this->assertArrayHasKey('occupancy_percent', $week);
        $this->assertArrayNotHasKey('forecast', $week);
        $this->assertLessThanOrEqual(3, count($week['top_channels']));
    }

    public function test_the_occupancy_forecast_tool_limits_the_number_of_days(): void
    {
        Room::factory()->create(['is_active' => true]);
        $catalog = app(CrmToolCatalog::class);

        $this->assertCount(7, $catalog->execute('get_occupancy_forecast', [])['days']);
        $this->assertCount(3, $catalog->execute('get_occupancy_forecast', ['days' => 3])['days']);
        $this->assertCount(30, $catalog->execute('get_occupancy_forecast', ['days' => 100])['days']);
        $this->assertCount(1, $catalog->execute('get_occupancy_forecast', ['days' => 0])['days']);
    }

    public function test_the_today_operations_tool_reports_the_morning_numbers(): void
    {
        $room = Room::factory()->create(['is_active' => true]);
        Booking::factory()->create(['room_id' => $room->id, 'status' => BookingStatus::Confirmed, 'check_in_date' => today(), 'check_out_date' => today()->addDays(2)]);

        $result = app(CrmToolCatalog::class)->execute('get_today_operations', []);

        $this->assertSame(1, $result['arrivals']);
        $this->assertSame(['arrivals', 'departures', 'in_house', 'rooms_out', 'open_notes', 'yesterday_cash'], array_keys($result));
    }

    public function test_the_new_hotel_tools_follow_the_users_booking_permission(): void
    {
        $this->artisan('access-control:sync');
        $profile = AiAssistantProfile::factory()->create(['allowed_tools' => ['get_hotel_report', 'get_occupancy_forecast', 'get_today_operations']]);

        $reportOnly = User::factory()->create();
        $reportOnly->assignRole(Role::create(['name' => 'فقط گزارش‌گیری'])->givePermissionTo('ai-assistant.report'));

        $withBookings = User::factory()->create();
        $withBookings->assignRole(Role::create(['name' => 'گزارش و رزرو'])->givePermissionTo(['ai-assistant.report', 'bookings.view_any']));

        $catalog = app(CrmToolCatalog::class);

        $this->assertSame([], array_keys($catalog->grantedTo($profile, $reportOnly)));
        $this->assertEqualsCanonicalizing(['get_hotel_report', 'get_occupancy_forecast', 'get_today_operations'], array_keys($catalog->grantedTo($profile, $withBookings)));
        $this->assertNotNull($catalog->label('get_hotel_report'));
    }
}
