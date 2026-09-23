<?php

namespace Database\Factories;

use App\Enums\DiscountType;
use App\Models\DiscountCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscountCode>
 */
class DiscountCodeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('SAVE????')),
            'discount_type' => DiscountType::Percentage,
            'discount_value' => 10,
            'used_count' => 0,
            'is_active' => true,
        ];
    }
}
