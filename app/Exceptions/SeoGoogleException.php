<?php

namespace App\Exceptions;

use RuntimeException;

class SeoGoogleException extends RuntimeException
{
    public static function notConfigured(): self
    {
        return new self('اتصال گوگل تنظیم نشده است. از منوی «تنظیمات اتصال گوگل» شناسه و کلید مخفی OAuth را وارد کنید.');
    }

    public static function requestFailed(string $reason): self
    {
        return new self("درخواست به سرویس گوگل ناموفق بود: {$reason}");
    }

    public static function notConnected(): self
    {
        return new self('این پروژه هنوز به Google Search Console متصل نشده است.');
    }
}
