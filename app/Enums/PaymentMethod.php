<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Cash = 'cash';
    case Card = 'card';
    case Online = 'online';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cash => 'نقدی',
            self::Card => 'کارت‌خوان',
            self::Online => 'آنلاین',
            self::Other => 'سایر',
        };
    }
}
