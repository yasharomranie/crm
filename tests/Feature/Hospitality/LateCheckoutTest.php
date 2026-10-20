<?php

namespace Tests\Feature\Hospitality;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LateCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function checkedIn(string $checkOutTime, int $checkOutDayOffset): Booking
    {
        $room = Room::factory()->create(['room_type_id' => RoomType::factory()->create(['check_out_time' => $checkOutTime])->id]);

        return Booking::factory()->create([
            'room_id' => $room->id,
            'status' => BookingStatus::CheckedIn,
            'check_in_date' => today()->subDays(2 - $checkOutDayOffset)->toDateString(),
            'check_out_date' => today()->addDays($checkOutDayOffset)->toDateString(),
        ]);
    }

    public function test_the_deadline_is_the_check_out_day_at_the_room_types_time(): void
    {
        $booking = $this->checkedIn('11:30', 0);

        $this->assertSame(today()->setTime(11, 30)->toDateTimeString(), $booking->checkOutDeadline()->toDateTimeString());
    }

    public function test_a_guest_is_late_only_after_the_check_out_time(): void
    {
        $booking = $this->checkedIn('12:00', 0);

        $this->travelTo(today()->setTime(11, 59));
        $this->assertFalse($booking->isPastCheckOutTime());

        $this->travelTo(today()->setTime(12, 1));
        $this->assertTrue($booking->isPastCheckOutTime());
    }

    public function test_a_guest_staying_on_a_later_day_is_not_late(): void
    {
        $booking = $this->checkedIn('12:00', 1);

        $this->travelTo(today()->setTime(23, 0));

        $this->assertFalse($booking->isPastCheckOutTime());
    }

    public function test_a_guest_who_already_left_is_not_late(): void
    {
        $booking = $this->checkedIn('12:00', -1);
        $booking->update(['status' => BookingStatus::CheckedOut]);

        $this->assertFalse($booking->fresh()->isPastCheckOutTime());
    }
}
