<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AdmissionTicketStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Active = 'active';
    case PartiallyUsed = 'partially_used';
    case Used = 'used';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case Blocked = 'blocked';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'پیش‌نویس',
            self::Active => 'فعال',
            self::PartiallyUsed => 'نیمه‌مصرف‌شده',
            self::Used => 'مصرف‌شده',
            self::Expired => 'منقضی',
            self::Cancelled => 'لغوشده',
            self::Refunded => 'مستردشده',
            self::Blocked => 'مسدود',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Active => 'success',
            self::PartiallyUsed => 'info',
            self::Used => 'gray',
            self::Expired => 'warning',
            self::Cancelled, self::Blocked => 'danger',
            self::Refunded => 'warning',
        };
    }
}
