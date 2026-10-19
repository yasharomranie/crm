<?php

namespace Database\Factories;

use App\Enums\BookingPaymentKind;
use App\Enums\BookingPaymentMethod;
use App\Models\Booking;
use App\Models\BookingPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingPayment>
 */
class BookingPaymentFactory extends Factory
{
    protected $model = BookingPayment::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'kind' => BookingPaymentKind::Payment,
            'method' => BookingPaymentMethod::Cash,
            'amount' => fake()->numberBetween(100_000, 1_000_000),
            'paid_at' => now(),
        ];
    }
}
