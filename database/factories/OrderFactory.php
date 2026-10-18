<?php

namespace Database\Factories;

use App\Enums\TicketSourceType;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'source_type' => TicketSourceType::Cashier,
            'original_amount' => 0,
            'final_amount' => 0,
            'currency' => config('ticketing.currency', 'IRT'),
        ];
    }
}
