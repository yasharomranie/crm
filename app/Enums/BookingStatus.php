<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BookingStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case CheckedOut = 'checked_out';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'در انتظار تأیید',
            self::Confirmed => 'رزرو قطعی',
            self::CheckedIn => 'چک‌این شده (مقیم)',
            self::CheckedOut => 'چک‌اوت شده (تسویه)',
            self::Cancelled => 'لغو شده',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Confirmed => 'warning',
            self::CheckedIn => 'info',
            self::CheckedOut => 'success',
            self::Cancelled => 'danger',
        };
    }
}
