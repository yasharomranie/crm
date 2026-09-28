<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SeoOpportunityStatus: string implements HasColor, HasLabel
{
    case Detected = 'detected';
    case Reviewed = 'reviewed';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Detected => 'شناسایی‌شده',
            self::Reviewed => 'بررسی‌شده',
            self::Resolved => 'برطرف‌شده',
            self::Dismissed => 'رد‌شده',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Detected => 'warning',
            self::Reviewed => 'info',
            self::Resolved => 'success',
            self::Dismissed => 'gray',
        };
    }
}
