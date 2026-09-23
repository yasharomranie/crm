<?php

namespace Database\Factories;

use App\Enums\AdmissionTicketStatus;
use App\Enums\TicketSourceType;
use App\Models\AdmissionTicket;
use App\Models\OrderItem;
use App\Models\TicketVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdmissionTicket>
 */
class AdmissionTicketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_item_id' => OrderItem::factory(),
            'ticket_variant_id' => fn (array $attrs) => OrderItem::find($attrs['order_item_id'])?->ticket_variant_id
                ?? TicketVariant::factory(),
            'status' => AdmissionTicketStatus::Active,
            'allowed_uses' => 1,
            'used_uses' => 0,
            'valid_from' => now(),
            'valid_until' => now()->addDay(),
            'source_type' => TicketSourceType::Cashier,
        ];
    }
}
