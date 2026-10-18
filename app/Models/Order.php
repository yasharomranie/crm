<?php

namespace App\Models;

use App\Enums\TicketSourceType;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'order_number', 'source_type', 'external_order_id', 'external_api_client_id',
        'customer_name', 'customer_phone',
        'discount_code_id', 'discount_code_snapshot', 'discount_type_snapshot', 'discount_value_snapshot',
        'original_amount', 'discount_amount', 'tax_amount', 'final_amount', 'amount_paid', 'currency',
        'created_by', 'cashier_shift_id',
    ];

    protected function casts(): array
    {
        return [
            'source_type' => TicketSourceType::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $order) {
            $order->order_number ??= 'ORD-'.now()->format('ymd').'-'.strtoupper(Str::random(6));
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function tickets(): HasManyThrough
    {
        return $this->hasManyThrough(AdmissionTicket::class, OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function complimentaryIssues(): HasMany
    {
        return $this->hasMany(ComplimentaryIssue::class);
    }

    public function discountCode(): BelongsTo
    {
        return $this->belongsTo(DiscountCode::class);
    }

    public function externalApiClient(): BelongsTo
    {
        return $this->belongsTo(ExternalApiClient::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
