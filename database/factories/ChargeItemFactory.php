<?php

namespace Database\Factories;

use App\Models\ChargeItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChargeItem>
 */
class ChargeItemFactory extends Factory
{
    protected $model = ChargeItem::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'unit_amount' => fake()->numberBetween(20, 500) * 1000,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
