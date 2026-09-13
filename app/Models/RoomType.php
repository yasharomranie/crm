<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomType extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'capacity', 'base_price', 'description', 'is_active'];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:0',
            'is_active' => 'boolean',
        ];
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function allotments(): HasMany
    {
        return $this->hasMany(ChannelAllotment::class);
    }

    public function priceRules(): HasMany
    {
        return $this->hasMany(PriceRule::class);
    }

    /**
     * The nightly rate for one specific date: the base price, adjusted by
     * the highest-priority active PriceRule that covers that date (doc2
     * "قیمت‌گذاری پویا"). Rules are simplified - only one, the
     * highest-priority match, applies per night; adjustments don't stack.
     */
    public function effectivePriceForDate(Carbon $date): int
    {
        $rule = $this->priceRules
            ->filter(fn (PriceRule $rule) => $rule->appliesToDate($date))
            ->sortByDesc('priority')
            ->first();

        return $rule ? $rule->adjust((int) $this->base_price) : (int) $this->base_price;
    }

    /**
     * Sum of nightly rates across a check-in/check-out range (checkout
     * night itself excluded, matching Booking::nights()).
     */
    public function effectivePriceForRange(Carbon $checkIn, Carbon $checkOut): int
    {
        $total = 0;
        $nights = max(1, $checkIn->diffInDays($checkOut));

        for ($i = 0; $i < $nights; $i++) {
            $total += $this->effectivePriceForDate($checkIn->copy()->addDays($i));
        }

        return $total;
    }
}
