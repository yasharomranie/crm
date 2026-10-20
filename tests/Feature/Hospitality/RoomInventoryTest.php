<?php

namespace Tests\Feature\Hospitality;

use App\Domain\Hospitality\HotelReport;
use App\Domain\Hospitality\NightAudit;
use App\Domain\Hospitality\OccupancyCalendar;
use App\Domain\Hospitality\RoomInventory;
use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomBlock;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomInventoryTest extends TestCase
{
    use RefreshDatabase;

    private function stay(Room $room, BookingStatus $status, int $fromDay, int $toDay): Booking
    {
        return Booking::factory()->create([
            'room_id' => $room->id,
            'status' => $status,
            'check_in_date' => today()->addDays($fromDay)->toDateString(),
            'check_out_date' => today()->addDays($toDay)->toDateString(),
        ]);
    }

    public function test_the_summary_is_derived_from_stays_and_blocks_not_from_stale_statuses(): void
    {
        // Stored «اشغال» but nobody is in it: it is sellable, not occupied.
        Room::factory()->create(['status' => RoomStatus::Occupied]);
        // Stored «خالی» but a guest is checked in: it is occupied.
        $this->stay(Room::factory()->create(['status' => RoomStatus::Available]), BookingStatus::CheckedIn, -1, 2);
        Room::factory()->create(['status' => RoomStatus::NeedsCleaning]);
        Room::factory()->create(['status' => RoomStatus::OutOfService]);
        Room::factory()->create(['is_active' => false]);

        $summary = app(RoomInventory::class)->summary();

        $this->assertSame(4, $summary['total']);
        $this->assertSame(3, $summary['sellable']);
        $this->assertSame(1, $summary['occupied']);
        $this->assertSame(1, $summary['needs_cleaning']);
        $this->assertSame(1, $summary['out_of_service']);
        $this->assertSame(1, $summary['available']);
        $this->assertSame(33.3, $summary['occupancy_percent']);
    }

    public function test_sellable_room_nights_leave_out_blocked_nights(): void
    {
        $room = Room::factory()->create();
        Room::factory()->create();
        RoomBlock::factory()->create([
            'room_id' => $room->id,
            'starts_on' => '2030-03-03',
            'ends_on' => '2030-03-06',
        ]);

        $inventory = app(RoomInventory::class);

        // Ten nights, two rooms, one of them blocked on the 3rd, 4th and 5th.
        $this->assertSame(17, $inventory->sellableRoomNights(Carbon::parse('2030-03-01'), Carbon::parse('2030-03-10')));
        $this->assertSame(1, $inventory->sellableOn(Carbon::parse('2030-03-04')));
        $this->assertSame(2, $inventory->sellableOn(Carbon::parse('2030-03-06')));
    }

    public function test_an_open_ended_block_removes_the_room_from_every_later_night(): void
    {
        $room = Room::factory()->create();
        RoomBlock::factory()->create(['room_id' => $room->id, 'starts_on' => '2030-03-05', 'ends_on' => null]);

        $perNight = app(RoomInventory::class)->sellablePerNight(Carbon::parse('2030-03-04'), Carbon::parse('2030-03-06'));

        $this->assertSame(['2030-03-04' => 1, '2030-03-05' => 0, '2030-03-06' => 0], $perNight);
    }

    public function test_occupancy_and_revpar_use_only_sellable_nights(): void
    {
        $sold = Room::factory()->create();
        $blocked = Room::factory()->create();
        RoomBlock::factory()->create(['room_id' => $blocked->id, 'starts_on' => '2030-03-01', 'ends_on' => '2030-03-11']);
        Booking::factory()->create([
            'room_id' => $sold->id,
            'status' => BookingStatus::Confirmed,
            'check_in_date' => '2030-03-01',
            'check_out_date' => '2030-03-06',
        ]);

        $report = app(HotelReport::class)->build(Carbon::parse('2030-03-01'), Carbon::parse('2030-03-10'));

        $this->assertSame(10, $report['room_nights_available']);
        $this->assertSame(50.0, $report['occupancy_percent']);
    }

    public function test_the_forecast_counts_only_sellable_units_each_night(): void
    {
        $room = Room::factory()->create();
        Room::factory()->create();
        RoomBlock::factory()->create(['room_id' => $room->id, 'starts_on' => today()->toDateString(), 'ends_on' => today()->addDay()->toDateString()]);

        $forecast = app(HotelReport::class)->forecast();

        $this->assertSame(1, $forecast[0]['available']);
        $this->assertSame(2, $forecast[1]['available']);
    }

    public function test_the_night_audit_measures_the_house_against_sellable_units(): void
    {
        $inUse = Room::factory()->create();
        $this->stay($inUse, BookingStatus::CheckedIn, -1, 2);
        Room::factory()->create();
        $broken = Room::factory()->create();
        RoomBlock::factory()->create(['room_id' => $broken->id, 'starts_on' => today()->subDay()->toDateString(), 'ends_on' => today()->addDay()->toDateString()]);

        $report = app(NightAudit::class)->build(today());

        $this->assertSame(2, $report['rooms']);
        $this->assertSame(50.0, $report['occupancy_percent']);
    }

    public function test_the_calendar_shows_blocked_nights_and_shrinks_the_daily_total(): void
    {
        $room = Room::factory()->create();
        RoomBlock::factory()->create(['room_id' => $room->id, 'starts_on' => '2030-03-02', 'ends_on' => '2030-03-04']);

        $calendar = app(OccupancyCalendar::class)->build(Carbon::parse('2030-03-01'), 4);

        $this->assertSame(['free', 'blocked', 'blocked', 'free'], array_column($calendar['rows'][0]['cells'], 'type'));
        $this->assertSame([1, 0, 0, 1], array_column($calendar['days'], 'sellable'));
    }

    public function test_every_availability_search_agrees_about_a_blocked_room(): void
    {
        $room = Room::factory()->create();
        RoomBlock::factory()->create(['room_id' => $room->id, 'starts_on' => today()->addDays(2)->toDateString(), 'ends_on' => today()->addDays(5)->toDateString()]);

        $this->assertFalse(Room::query()->freeBetween(today()->addDays(3), today()->addDays(4))->whereKey($room->id)->exists());
        $this->assertTrue(Room::query()->freeBetween(today()->addDays(5), today()->addDays(7))->whereKey($room->id)->exists());
    }

    public function test_a_back_to_back_stay_does_not_block_the_next_guest(): void
    {
        $room = Room::factory()->create();
        $this->stay($room, BookingStatus::Confirmed, 0, 3);

        $this->assertTrue(Room::query()->freeBetween(today()->addDays(3), today()->addDays(5))->whereKey($room->id)->exists());
        $this->assertFalse(Room::query()->freeBetween(today()->addDays(2), today()->addDays(5))->whereKey($room->id)->exists());
    }
}
