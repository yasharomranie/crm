<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Exceptions\RoomNotAvailableException;
use App\Models\Booking;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingOverlapPreventionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_a_new_booking_that_overlaps_an_existing_one_on_the_same_room(): void
    {
        $room = Room::factory()->create();

        Booking::factory()->create([
            'room_id' => $room->id,
            'check_in_date' => '2027-01-10',
            'check_out_date' => '2027-01-15',
            'status' => BookingStatus::Confirmed,
        ]);

        $this->expectException(RoomNotAvailableException::class);

        Booking::factory()->create([
            'room_id' => $room->id,
            'check_in_date' => '2027-01-12',
            'check_out_date' => '2027-01-18',
            'status' => BookingStatus::Confirmed,
        ]);
    }

    public function test_it_allows_a_new_booking_that_does_not_overlap(): void
    {
        $room = Room::factory()->create();

        Booking::factory()->create([
            'room_id' => $room->id,
            'check_in_date' => '2027-01-10',
            'check_out_date' => '2027-01-15',
            'status' => BookingStatus::Confirmed,
        ]);

        $booking = Booking::factory()->create([
            'room_id' => $room->id,
            'check_in_date' => '2027-01-15',
            'check_out_date' => '2027-01-18',
            'status' => BookingStatus::Confirmed,
        ]);

        $this->assertTrue($booking->wasRecentlyCreated);
    }

    public function test_it_allows_a_new_booking_on_the_same_dates_when_the_existing_one_is_cancelled(): void
    {
        $room = Room::factory()->create();

        Booking::factory()->create([
            'room_id' => $room->id,
            'check_in_date' => '2027-01-10',
            'check_out_date' => '2027-01-15',
            'status' => BookingStatus::Cancelled,
        ]);

        $booking = Booking::factory()->create([
            'room_id' => $room->id,
            'check_in_date' => '2027-01-10',
            'check_out_date' => '2027-01-15',
            'status' => BookingStatus::Confirmed,
        ]);

        $this->assertTrue($booking->wasRecentlyCreated);
    }

    public function test_it_rejects_editing_a_booking_into_a_conflict_with_another_booking(): void
    {
        $room = Room::factory()->create();

        Booking::factory()->create([
            'room_id' => $room->id,
            'check_in_date' => '2027-02-10',
            'check_out_date' => '2027-02-15',
            'status' => BookingStatus::Confirmed,
        ]);

        $movable = Booking::factory()->create([
            'room_id' => $room->id,
            'check_in_date' => '2027-03-01',
            'check_out_date' => '2027-03-05',
            'status' => BookingStatus::Confirmed,
        ]);

        $this->expectException(RoomNotAvailableException::class);

        $movable->update(['check_in_date' => '2027-02-12']);
    }

    public function test_it_allows_editing_a_bookings_own_dates_without_treating_itself_as_a_conflict(): void
    {
        $room = Room::factory()->create();

        $booking = Booking::factory()->create([
            'room_id' => $room->id,
            'check_in_date' => '2027-04-10',
            'check_out_date' => '2027-04-15',
            'status' => BookingStatus::Confirmed,
        ]);

        $booking->update(['check_out_date' => '2027-04-16']);

        $this->assertSame('2027-04-16', $booking->fresh()->check_out_date->toDateString());
    }

    public function test_it_allows_cancelling_a_booking_even_if_it_would_otherwise_conflict(): void
    {
        $room = Room::factory()->create();

        Booking::factory()->create([
            'room_id' => $room->id,
            'check_in_date' => '2027-05-10',
            'check_out_date' => '2027-05-15',
            'status' => BookingStatus::Confirmed,
        ]);

        $booking = Booking::factory()->create([
            'room_id' => $room->id,
            'check_in_date' => '2027-06-01',
            'check_out_date' => '2027-06-05',
            'status' => BookingStatus::Confirmed,
        ]);

        $booking->update(['status' => BookingStatus::Cancelled]);

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
    }
}
