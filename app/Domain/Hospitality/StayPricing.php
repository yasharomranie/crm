<?php

namespace App\Domain\Hospitality;

use App\Models\BookingPromoCode;
use App\Models\RoomType;
use App\Models\StayDiscount;
use Carbon\CarbonInterface;

/**
 * Prices one stay of a room type: the nightly rates (base price moved by the
 * date/weekday rules), the extra adults and children, the long-stay discount
 * and a promo code. The same quote prices a booking's folio, the estimate in
 * the booking form and the external API, so they can never disagree.
 *
 * Folio lines come out as [title, amount] pairs; discounts are negative.
 */
class StayPricing
{
    /**
     * @return array{nights: int, night_rates: list<array{date: string, rate: int}>, room_total: int, extra_guests_total: int, stay_discount_percent: float, stay_discount: int, promo_code: ?BookingPromoCode, promo_discount: int, total: int, minimum_nights: int}
     */
    public function quote(RoomType $roomType, CarbonInterface $checkIn, CarbonInterface $checkOut, int $adults = 1, int $children = 0, ?BookingPromoCode $promo = null): array
    {
        $nights = max(1, (int) $checkIn->copy()->startOfDay()->diffInDays($checkOut->copy()->startOfDay()));

        $rates = [];

        for ($i = 0; $i < $nights; $i++) {
            $date = $checkIn->copy()->startOfDay()->addDays($i);
            $rates[] = ['date' => $date->toDateString(), 'rate' => $roomType->effectivePriceForDate($date)];
        }

        $roomTotal = array_sum(array_column($rates, 'rate'));
        $extraGuests = $this->extraGuestsTotal($roomType, $nights, $adults, $children);

        $tier = $this->bestStayDiscount($roomType, $nights);
        $stayDiscount = $tier ? (int) round($roomTotal * (float) $tier->percent / 100) : 0;

        $promoDiscount = $promo?->discountFor($roomTotal - $stayDiscount) ?? 0;

        return [
            'nights' => $nights,
            'night_rates' => $rates,
            'room_total' => $roomTotal,
            'extra_guests_total' => $extraGuests,
            'stay_discount_percent' => $tier ? (float) $tier->percent : 0.0,
            'stay_discount' => $stayDiscount,
            'promo_code' => $promo,
            'promo_discount' => $promoDiscount,
            'total' => $roomTotal + $extraGuests - $stayDiscount - $promoDiscount,
            'minimum_nights' => $roomType->minimumNightsFor($checkIn, $checkOut),
        ];
    }

    /**
     * The folio lines for a quote. The first line is the room itself, so a
     * stay without extras or discounts looks exactly as it always did.
     *
     * @param  array<string, mixed>  $quote
     * @return list<array{title: string, amount: int}>
     */
    public function folioLines(array $quote, int $adults, int $children): array
    {
        $lines = [['title' => "اقامت {$quote['nights']} شب", 'amount' => $quote['room_total']]];

        if ($quote['extra_guests_total'] > 0) {
            $lines[] = ['title' => "هزینه‌ی نفرات اضافه ({$adults} بزرگسال، {$children} کودک)", 'amount' => $quote['extra_guests_total']];
        }

        if ($quote['stay_discount'] > 0) {
            $lines[] = ['title' => 'تخفیف اقامت طولانی ('.rtrim(rtrim(number_format($quote['stay_discount_percent'], 2), '0'), '.').'٪)', 'amount' => -$quote['stay_discount']];
        }

        if ($quote['promo_discount'] > 0) {
            $lines[] = ['title' => "کد تخفیف {$quote['promo_code']->code}", 'amount' => -$quote['promo_discount']];
        }

        return $lines;
    }

    private function extraGuestsTotal(RoomType $roomType, int $nights, int $adults, int $children): int
    {
        $extraAdults = $roomType->base_occupancy === null ? 0 : max(0, $adults - $roomType->base_occupancy);

        return $nights * ($extraAdults * (int) $roomType->extra_adult_fee + max(0, $children) * (int) $roomType->extra_child_fee);
    }

    private function bestStayDiscount(RoomType $roomType, int $nights): ?StayDiscount
    {
        return StayDiscount::query()
            ->where('is_active', true)
            ->where('min_nights', '<=', $nights)
            ->where(fn ($query) => $query->whereNull('room_type_id')->orWhere('room_type_id', $roomType->id))
            ->orderByDesc('percent')
            ->first();
    }
}
