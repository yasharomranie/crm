<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum BookingPaymentMethod: string implements HasLabel
{
    case Cash = 'cash';
    case Card = 'card';
    case Transfer = 'transfer';
    case Online = 'online';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cash => 'نقدی',
            self::Card => 'کارت‌خوان',
            self::Transfer => 'کارت‌به‌کارت / حواله',
            self::Online => 'آنلاین',
            self::Other => 'سایر',
        };
    }
}
