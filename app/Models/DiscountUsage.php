<?php

namespace App\Models;

use Database\Factories\DiscountUsageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscountUsage extends Model
{
    /** @use HasFactory<DiscountUsageFactory> */
    use HasFactory;

    protected $fillable = ['discount_code_id', 'order_id', 'customer_phone', 'discount_amount'];

    public function discountCode(): BelongsTo
    {
        return $this->belongsTo(DiscountCode::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
