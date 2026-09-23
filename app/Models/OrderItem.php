<?php

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id', 'product_id', 'ticket_variant_id', 'quantity',
        'unit_price', 'original_amount', 'discount_amount', 'tax_amount', 'final_amount',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function ticketVariant(): BelongsTo
    {
        return $this->belongsTo(TicketVariant::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(AdmissionTicket::class);
    }
}
