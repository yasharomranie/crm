<?php

namespace Database\Factories;

use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomType>
 */
class RoomTypeFactory extends Factory
{
    protected $model = RoomType::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'capacity' => fake()->numberBetween(2, 6),
            'base_price' => fake()->numberBetween(800_000, 5_000_000),
            'is_active' => true,
        ];
    }
}
