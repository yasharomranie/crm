<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SalesChannelType: string implements HasLabel
{
    case Ota = 'ota';
    case Direct = 'direct';

    public function getLabel(): string
    {
        return match ($this) {
            self::Ota => 'سایت واسط (OTA)',
            self::Direct => 'فروش مستقیم',
        };
    }
}
