<?php

namespace App\Models;

use App\Enums\DiscountType;
use Database\Factories\DiscountCodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class DiscountCode extends Model
{
    /** @use HasFactory<DiscountCodeFactory> */
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty()->useLogName('discount_code');
    }

    protected $fillable = [
        'code', 'discount_type', 'discount_value', 'start_date', 'end_date',
        'usage_limit', 'used_count', 'per_customer_usage_limit',
        'minimum_purchase_amount', 'maximum_discount_amount', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'discount_type' => DiscountType::class,
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    public function ticketVariants(): BelongsToMany
    {
        return $this->belongsToMany(TicketVariant::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(DiscountUsage::class);
    }

    /**
     * All the server-side checks from section 27-29, run BEFORE any
     * attempt to consume usage (see DiscountService::apply(), which locks
     * the row and re-checks usage_limit atomically - this method alone
     * is not concurrency-safe by itself, it's the pre-check).
     *
     * @return string|null a human-readable rejection reason, or null if eligible
     */
    public function eligibilityError(
        TicketVariant $variant,
        int $subtotal,
        ?string $customerPhone,
        ?Carbon $at = null,
    ): ?string {
        $at ??= Carbon::now();

        if (! $this->is_active) {
            return 'این کد تخفیف غیرفعال است.';
        }

        if ($this->start_date && $at->lessThan($this->start_date)) {
            return 'این کد تخفیف هنوز فعال نشده است.';
        }

        if ($this->end_date && $at->greaterThan($this->end_date)) {
            return 'این کد تخفیف منقضی شده است.';
        }

        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return 'ظرفیت استفاده از این کد تخفیف تمام شده است.';
        }

        if ($this->minimum_purchase_amount !== null && $subtotal < $this->minimum_purchase_amount) {
            return 'مبلغ خرید کمتر از حداقل لازم برای این کد تخفیف است.';
        }

        if ($this->products()->exists() && ! $this->products()->whereKey($variant->product_id)->exists()) {
            return 'این کد تخفیف برای این محصول قابل استفاده نیست.';
        }

        if ($this->ticketVariants()->exists() && ! $this->ticketVariants()->whereKey($variant->id)->exists()) {
            return 'این کد تخفیف برای این نوع بلیت قابل استفاده نیست.';
        }

        if ($customerPhone && $this->per_customer_usage_limit !== null) {
            $customerUsages = $this->usages()->where('customer_phone', $customerPhone)->count();

            if ($customerUsages >= $this->per_customer_usage_limit) {
                return 'سقف استفاده‌ی این مشتری از این کد تخفیف پر شده است.';
            }
        }

        return null;
    }

    public function calculateDiscount(int $amount): int
    {
        $discount = $this->discount_type === DiscountType::Percentage
            ? (int) round($amount * $this->discount_value / 100)
            : $this->discount_value;

        $discount = min($discount, $amount);

        if ($this->maximum_discount_amount !== null) {
            $discount = min($discount, $this->maximum_discount_amount);
        }

        return $discount;
    }
}
