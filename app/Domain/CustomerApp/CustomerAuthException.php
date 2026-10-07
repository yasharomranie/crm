<?php

namespace App\Domain\CustomerApp;

use Exception;

/**
 * Every message thrown from this domain is written to be shown directly
 * to the customer in the وب‌اپ مشتریان (Persian, no internal detail).
 */
class CustomerAuthException extends Exception
{
    public static function googleNotConfigured(): self
    {
        return new self('ورود با گوگل در حال حاضر فعال نیست.');
    }

    public static function googleRequestFailed(string $reason): self
    {
        return new self("ورود با گوگل ناموفق بود: {$reason}");
    }
}
