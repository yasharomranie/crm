<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\TicketVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        $unitPrice = fake()->numberBetween(100000, 1000000);

        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'ticket_variant_id' => TicketVariant::factory(),
            'quantity' => 1,
            'unit_price' => $unitPrice,
            'original_amount' => $unitPrice,
            'final_amount' => $unitPrice,
        ];
    }
}
