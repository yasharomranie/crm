<?php

namespace Database\Factories;

use App\Models\DiscountCode;
use App\Models\DiscountUsage;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscountUsage>
 */
class DiscountUsageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'discount_code_id' => DiscountCode::factory(),
            'order_id' => Order::factory(),
            'discount_amount' => fake()->numberBetween(10000, 100000),
        ];
    }
}
