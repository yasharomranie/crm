<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\TicketVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketVariant>
 */
class TicketVariantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => fake()->words(2, true),
            'code' => strtoupper(fake()->unique()->lexify('VAR-????')),
            'allowed_uses' => 1,
            'direction' => null,
            'default_validity_days' => 1,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function outbound(): static
    {
        return $this->state(['direction' => 'outbound', 'allowed_uses' => 1]);
    }

    public function return(): static
    {
        return $this->state(['direction' => 'return', 'allowed_uses' => 1]);
    }

    public function roundTrip(): static
    {
        return $this->state(['direction' => null, 'allowed_uses' => 2]);
    }
}
