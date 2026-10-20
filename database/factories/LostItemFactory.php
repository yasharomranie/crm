<?php

namespace Database\Factories;

use App\Models\LostItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LostItem>
 */
class LostItemFactory extends Factory
{
    protected $model = LostItem::class;

    public function definition(): array
    {
        return [
            'description' => fake()->words(3, true),
            'location' => 'اتاق',
            'found_on' => now()->toDateString(),
            'status' => LostItem::STATUS_FOUND,
        ];
    }
}
