<?php

namespace Database\Factories;

use App\Models\ComplimentaryIssue;
use App\Models\Order;
use App\Models\Product;
use App\Models\TicketVariant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComplimentaryIssue>
 */
class ComplimentaryIssueFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'ticket_variant_id' => TicketVariant::factory(),
            'quantity' => 1,
            'original_value' => fake()->numberBetween(100000, 500000),
            'reason' => 'مهمان ویژه',
            'issued_by' => User::factory(),
            'issued_at' => now(),
        ];
    }
}
