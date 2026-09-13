<?php

namespace App\Exceptions;

use RuntimeException;

class AiProviderException extends RuntimeException
{
    public static function noActiveProvider(): self
    {
        return new self('هیچ ارائه‌دهندهٔ هوش مصنوعی فعالی تنظیم نشده است. از بخش «تنظیمات هوش مصنوعی» یک ارائه‌دهنده اضافه و فعال کنید.');
    }

    public static function requestFailed(string $reason): self
    {
        return new self("درخواست به سرویس هوش مصنوعی ناموفق بود: {$reason}");
    }
}
