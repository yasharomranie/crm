<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LoyaltyTier: string implements HasColor, HasLabel
{
    case Bronze = 'bronze';
    case Silver = 'silver';
    case Gold = 'gold';
    case Vip = 'vip';

    public function getLabel(): string
    {
        return match ($this) {
            self::Bronze => 'برنزی',
            self::Silver => 'نقره‌ای',
            self::Gold => 'طلایی',
            self::Vip => 'VIP',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Bronze => 'gray',
            self::Silver => 'info',
            self::Gold => 'warning',
            self::Vip => 'success',
        };
    }
}
