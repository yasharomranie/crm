<?php

namespace App\Domain\Ticketing;

use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\TicketVariant;

/**
 * Called only from inside TicketIssuer::issue()'s own DB transaction -
 * lockAndValidate() takes a row lock on the discount code for the
 * lifetime of that transaction, so two concurrent orders racing to use
 * the last unit of a limited code serialize on this lock instead of both
 * reading a stale used_count (section 29's concurrency requirement).
 */
class DiscountService
{
    public function lockAndValidate(string $code, int $orderSubtotal, ?string $customerPhone): DiscountCode
    {
        $discountCode = DiscountCode::where('code', $code)->lockForUpdate()->first();

        if (! $discountCode) {
            throw new TicketingException('کد تخفیف نامعتبر است.');
        }

        // Every check below is order-level (active/date range/usage
        // limits/min purchase/per-customer). Product/variant restriction
        // is a per-line concern, checked separately per item via
        // variantIsEligible() - DiscountCode::eligibilityError() bundles
        // both together and is meant for a single-variant context (the
        // Filament form's own preview), not this order-wide gate.
        if (! $discountCode->is_active) {
            throw new TicketingException('این کد تخفیف غیرفعال است.');
        }

        $now = now();

        if ($discountCode->start_date && $now->lessThan($discountCode->start_date)) {
            throw new TicketingException('این کد تخفیف هنوز فعال نشده است.');
        }

        if ($discountCode->end_date && $now->greaterThan($discountCode->end_date)) {
            throw new TicketingException('این کد تخفیف منقضی شده است.');
        }

        if ($discountCode->usage_limit !== null && $discountCode->used_count >= $discountCode->usage_limit) {
            throw new TicketingException('ظرفیت استفاده از این کد تخفیف تمام شده است.');
        }

        if ($discountCode->minimum_purchase_amount !== null && $orderSubtotal < $discountCode->minimum_purchase_amount) {
            throw new TicketingException('مبلغ خرید کمتر از حداقل لازم برای این کد تخفیف است.');
        }

        if ($customerPhone && $discountCode->per_customer_usage_limit !== null) {
            $customerUsages = $discountCode->usages()->where('customer_phone', $customerPhone)->count();

            if ($customerUsages >= $discountCode->per_customer_usage_limit) {
                throw new TicketingException('سقف استفاده‌ی این مشتری از این کد تخفیف پر شده است.');
            }
        }

        return $discountCode;
    }

    public function variantIsEligible(DiscountCode $code, TicketVariant $variant): bool
    {
        if ($code->products()->exists() && ! $code->products()->whereKey($variant->product_id)->exists()) {
            return false;
        }

        if ($code->ticketVariants()->exists() && ! $code->ticketVariants()->whereKey($variant->id)->exists()) {
            return false;
        }

        return true;
    }

    /**
     * The atomic increment itself: WHERE used_count < usage_limit (or no
     * limit at all) is what makes this safe under concurrency - if two
     * transactions both passed lockAndValidate() (impossible under the
     * row lock above, but this is the belt-and-suspenders match for that
     * lock) the second UPDATE affecting 0 rows is what actually throws
     * here, not a re-read of a stale count.
     */
    public function recordUsage(DiscountCode $discountCode, Order $order, int $discountAmount, ?string $customerPhone): void
    {
        $affected = DiscountCode::whereKey($discountCode->id)
            ->where(fn ($q) => $q->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'))
            ->increment('used_count');

        if (! $affected) {
            throw new TicketingException('ظرفیت استفاده از این کد تخفیف تمام شده است.');
        }

        $discountCode->usages()->create([
            'order_id' => $order->id,
            'customer_phone' => $customerPhone,
            'discount_amount' => $discountAmount,
        ]);
    }
}
