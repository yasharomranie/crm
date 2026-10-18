<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a deal's quote ("۳ شب ویلا"، "۲ بلیت تله‌کابین"). Once a deal
 * has lines, the deal's value is their sum - see Deal::syncValueFromItems().
 */
class DealItem extends Model
{
    use HasFactory;

    protected $fillable = ['deal_id', 'product_id', 'description', 'quantity', 'unit_price', 'discount_percent'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'integer',
            'discount_percent' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn (self $item) => $item->deal?->syncValueFromItems());
        static::deleted(fn (self $item) => Deal::find($item->deal_id)?->syncValueFromItems());
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function lineTotal(): int
    {
        return (int) round($this->quantity * $this->unit_price * (100 - $this->discount_percent) / 100);
    }
}
