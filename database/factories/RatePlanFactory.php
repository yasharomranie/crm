<?php

namespace Database\Factories;

use App\Enums\PriceAdjustmentType;
use App\Models\RatePlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RatePlan>
 */
class RatePlanFactory extends Factory
{
    protected $model = RatePlan::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'adjustment_type' => PriceAdjustmentType::Percentage,
            'adjustment_value' => 0,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
