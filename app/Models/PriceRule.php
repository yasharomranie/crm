<?php

namespace App\Models;

use App\Enums\PriceAdjustmentType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A dated price change for one room type. With «weekdays» filled it applies
 * only on those days of the week (Carbon numbering: 0 = Sunday ... 6 =
 * Saturday), e.g. +30% on Thursday and Friday nights of a season; a rule can
 * also demand a minimum stay for the nights it covers.
 */
class PriceRule extends Model
{
    protected $fillable = [
        'room_type_id',
        'name',
        'start_date',
        'end_date',
        'adjustment_type',
        'adjustment_value',
        'priority',
        'is_active',
        'weekdays',
        'min_nights',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'adjustment_type' => PriceAdjustmentType::class,
            'adjustment_value' => 'decimal:2',
            'priority' => 'integer',
            'is_active' => 'boolean',
            'weekdays' => 'array',
            'min_nights' => 'integer',
        ];
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function appliesToDate(Carbon $date): bool
    {
        return $this->is_active
            && $date->between($this->start_date, $this->end_date)
            && (blank($this->weekdays) || in_array($date->dayOfWeek, array_map('intval', $this->weekdays), true));
    }

    public function adjust(int $basePrice): int
    {
        return (int) match ($this->adjustment_type) {
            PriceAdjustmentType::Percentage => $basePrice + ($basePrice * $this->adjustment_value / 100),
            PriceAdjustmentType::Fixed => $basePrice + $this->adjustment_value,
        };
    }
}
