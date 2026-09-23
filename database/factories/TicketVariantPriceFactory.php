<?php

namespace Database\Factories;

use App\Models\TicketVariant;
use App\Models\TicketVariantPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketVariantPrice>
 */
class TicketVariantPriceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ticket_variant_id' => TicketVariant::factory(),
            'amount' => fake()->numberBetween(100000, 1000000),
            'currency' => 'IRR',
            'effective_from' => now()->subDay(),
            'effective_until' => null,
        ];
    }
}
