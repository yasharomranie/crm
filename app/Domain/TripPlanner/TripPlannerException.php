<?php

namespace App\Domain\TripPlanner;

use Exception;

/**
 * Every message thrown from this domain is written to be shown directly
 * to the customer in the وب‌اپ مشتریان (Persian, no internal detail).
 */
class TripPlannerException extends Exception
{
    public static function disabled(): self
    {
        return new self('تریپ‌پلنر در حال حاضر غیرفعال است. لطفاً بعداً دوباره تلاش کنید.');
    }

    public static function noEligibleAttractions(): self
    {
        return new self('در حال حاضر جاذبه فعالی برای برنامه‌ریزی ثبت نشده است.');
    }

    public static function invalidAiResponse(): self
    {
        return new self('دستیار هوشمند نتوانست برنامه معتبری بسازد. لطفاً دوباره تلاش کنید.');
    }
}
