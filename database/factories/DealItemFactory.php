<?php

namespace Database\Factories;

use App\Models\DealItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DealItem>
 */
class DealItemFactory extends Factory
{
    protected $model = DealItem::class;

    public function definition(): array
    {
        return [
            'description' => 'اقامت '.fake()->numberBetween(1, 5).' شب',
            'quantity' => 1,
            'unit_price' => fake()->numberBetween(5, 50) * 100_000,
            'discount_percent' => 0,
        ];
    }
}
