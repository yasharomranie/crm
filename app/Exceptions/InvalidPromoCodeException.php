<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A booking promo code that cannot be used; the message says why, in words
 * the front desk can read to the guest.
 */
class InvalidPromoCodeException extends RuntimeException
{
    public static function unknown(): self
    {
        return new self('این کد تخفیف وجود ندارد یا غیرفعال است.');
    }

    public static function notYetValid(): self
    {
        return new self('زمان استفاده از این کد تخفیف هنوز نرسیده است.');
    }

    public static function expired(): self
    {
        return new self('مهلت استفاده از این کد تخفیف تمام شده است.');
    }

    public static function exhausted(): self
    {
        return new self('سقف تعداد استفاده از این کد تخفیف پر شده است.');
    }

    public static function tooShort(int $minNights): self
    {
        return new self("این کد تخفیف برای اقامت حداقل {$minNights} شب است.");
    }
}
