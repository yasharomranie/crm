<?php

namespace Tests\Feature;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAppBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_view_bookings_without_logging_in(): void
    {
        $this->get(route('customer-app.bookings'))
            ->assertRedirect(route('customer-app.auth.login'));
    }

    public function test_searching_available_dates_lists_free_rooms_with_their_price(): void
    {
        $guest = Guest::factory()->create();
        $room = Room::factory()->create();

        $response = $this->actingAs($guest, 'customer')
            ->get(route('customer-app.bookings', [
                'check_in_date' => '2026-12-01',
                'check_out_date' => '2026-12-04',
            ]));

        $response->assertOk();
        $response->assertSee($room->name);
    }

    public function test_searching_excludes_a_room_already_booked_in_that_range(): void
    {
        $guest = Guest::factory()->create();
        $room = Room::factory()->create();

        Booking::factory()->create([
            'room_id' => $room->id,
            'check_in_date' => '2026-12-02',
            'check_out_date' => '2026-12-05',
            'status' => BookingStatus::Confirmed,
        ]);

        $response = $this->actingAs($guest, 'customer')
            ->get(route('customer-app.bookings', [
                'check_in_date' => '2026-12-01',
                'check_out_date' => '2026-12-04',
            ]));

        $response->assertOk();
        $response->assertDontSee($room->name);
    }

    public function test_a_guest_can_book_an_available_room(): void
    {
        $guest = Guest::factory()->create();
        $room = Room::factory()->create();

        $response = $this->actingAs($guest, 'customer')
            ->post(route('customer-app.bookings.store'), [
                'room_id' => $room->id,
                'check_in_date' => '2026-12-01',
                'check_out_date' => '2026-12-04',
            ]);

        $response->assertRedirect(route('customer-app.bookings'));

        $booking = Booking::where('room_id', $room->id)->where('guest_id', $guest->id)->first();
        $this->assertNotNull($booking);
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertSame(BookingSource::Online, $booking->source);
    }

    public function test_a_guest_cannot_double_book_an_already_reserved_room(): void
    {
        $guest = Guest::factory()->create();
        $room = Room::factory()->create();

        Booking::factory()->create([
            'room_id' => $room->id,
            'check_in_date' => '2026-12-02',
            'check_out_date' => '2026-12-05',
            'status' => BookingStatus::Confirmed,
        ]);

        $response = $this->actingAs($guest, 'customer')
            ->post(route('customer-app.bookings.store'), [
                'room_id' => $room->id,
                'check_in_date' => '2026-12-01',
                'check_out_date' => '2026-12-04',
            ]);

        $response->assertSessionHasErrors('room_id');
        $this->assertSame(1, Booking::where('room_id', $room->id)->count());
    }

    public function test_a_guest_only_sees_their_own_booking_history(): void
    {
        $guest = Guest::factory()->create();
        $otherGuest = Guest::factory()->create();

        $myBooking = Booking::factory()->create(['guest_id' => $guest->id]);
        $otherBooking = Booking::factory()->create(['guest_id' => $otherGuest->id]);

        $response = $this->actingAs($guest, 'customer')->get(route('customer-app.bookings'));

        $response->assertSee($myBooking->reference_code);
        $response->assertDontSee($otherBooking->reference_code);
    }
}
