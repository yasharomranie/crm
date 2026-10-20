<?php

namespace App\Models;

use App\Enums\PriceAdjustmentType;
use Database\Factories\RatePlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A way of selling the same room at another price and terms («با صبحانه»،
 * «بدون صبحانه»، «غیرقابل بازگشت»): every night of the stay is moved by a
 * percentage or a fixed amount from the night's normal rate (base price plus
 * date/weekday rules), and the plan may bring its own cancellation policy.
 * A booking without a plan is sold at the normal rate.
 */
class RatePlan extends Model
{
    /** @use HasFactory<RatePlanFactory> */
    use HasFactory;

    protected $fillable = ['name', 'description', 'adjustment_type', 'adjustment_value', 'cancellation_policy_id', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'adjustment_type' => PriceAdjustmentType::class,
            'adjustment_value' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function cancellationPolicy(): BelongsTo
    {
        return $this->belongsTo(CancellationPolicy::class);
    }

    /**
     * One night's rate under this plan (never below zero).
     */
    public function adjust(int $nightRate): int
    {
        $adjusted = $this->adjustment_type === PriceAdjustmentType::Percentage
            ? $nightRate + (int) round($nightRate * (float) $this->adjustment_value / 100)
            : $nightRate + (int) $this->adjustment_value;

        return max(0, $adjusted);
    }
}
