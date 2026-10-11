<?php

namespace Tests\Feature;

use App\Domain\Ai\CrmToolCatalog;
use App\Enums\BookingStatus;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\GuestTicket;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
