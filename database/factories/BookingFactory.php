<?php

namespace Database\Factories;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $checkIn = fake()->dateTimeBetween('+1 day', '+30 days');

        return [
            'room_id' => Room::factory(),
            'guest_id' => Guest::factory(),
            'check_in_date' => $checkIn,
            'check_out_date' => (clone $checkIn)->modify('+'.fake()->numberBetween(1, 5).' days'),
            'adults_count' => fake()->numberBetween(1, 3),
            'status' => BookingStatus::Confirmed,
            'source' => BookingSource::WalkIn,
        ];
    }
}
