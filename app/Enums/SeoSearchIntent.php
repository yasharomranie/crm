<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SeoSearchIntent: string implements HasColor, HasLabel
{
    case Informational = 'informational';
    case Commercial = 'commercial';
    case Transactional = 'transactional';
    case Navigational = 'navigational';
    case Local = 'local';

    public function getLabel(): string
    {
        return match ($this) {
            self::Informational => 'اطلاعاتی',
            self::Commercial => 'مقایسه‌ای/تجاری',
            self::Transactional => 'تراکنشی/خرید',
            self::Navigational => 'مسیریابی به برند',
            self::Local => 'محلی',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Informational => 'info',
            self::Commercial => 'warning',
            self::Transactional => 'success',
            self::Navigational => 'gray',
            self::Local => 'primary',
        };
    }
}
