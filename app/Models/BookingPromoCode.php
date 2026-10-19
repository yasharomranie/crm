<?php

namespace App\Models;

use App\Enums\PriceAdjustmentType;
use App\Exceptions\InvalidPromoCodeException;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * A code the guest gives when booking («کد تخفیف رزرو»): a percentage or a
 * fixed amount off the room nights. Separate from the ticket discount codes,
 * which are for the ticket office.
 */
class BookingPromoCode extends Model
{
    protected $fillable = [
        'code', 'name', 'discount_type', 'discount_value', 'valid_from', 'valid_until',
        'min_nights', 'usage_limit', 'used_count', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_type' => PriceAdjustmentType::class,
            'discount_value' => 'decimal:2',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'min_nights' => 'integer',
            'usage_limit' => 'integer',
            'used_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public static function normalize(string $code): string
    {
        return mb_strtoupper(trim($code));
    }

    /**
     * Finds the code and checks it can be used for a stay of $nights nights.
     *
     * @throws InvalidPromoCodeException
     */
    public static function resolve(string $code, int $nights, ?CarbonInterface $today = null): self
    {
        $promo = static::query()->where('code', static::normalize($code))->first();

        if (! $promo) {
            throw InvalidPromoCodeException::unknown();
        }

        $promo->assertUsable($nights, $today);

        return $promo;
    }

    /**
     * @throws InvalidPromoCodeException
     */
    public function assertUsable(int $nights, ?CarbonInterface $today = null): void
    {
        $today ??= today();

        match (true) {
            ! $this->is_active => throw InvalidPromoCodeException::unknown(),
            $this->valid_from && $today->lt($this->valid_from) => throw InvalidPromoCodeException::notYetValid(),
            $this->valid_until && $today->gt($this->valid_until) => throw InvalidPromoCodeException::expired(),
            $this->usage_limit !== null && $this->used_count >= $this->usage_limit => throw InvalidPromoCodeException::exhausted(),
            $this->min_nights && $nights < $this->min_nights => throw InvalidPromoCodeException::tooShort($this->min_nights),
            default => null,
        };
    }

    /**
     * The discount on $amount (never more than the amount itself).
     */
    public function discountFor(int $amount): int
    {
        $discount = $this->discount_type === PriceAdjustmentType::Percentage
            ? (int) round($amount * (float) $this->discount_value / 100)
            : (int) $this->discount_value;

        return max(0, min($amount, $discount));
    }
}
