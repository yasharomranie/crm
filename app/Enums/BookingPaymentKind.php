<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BookingPaymentKind: string implements HasColor, HasLabel
{
    case Payment = 'payment';
    case Deposit = 'deposit';
    case Refund = 'refund';

    public function getLabel(): string
    {
        return match ($this) {
            self::Payment => 'پرداخت',
            self::Deposit => 'پیش‌پرداخت',
            self::Refund => 'استرداد',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Payment => 'success',
            self::Deposit => 'info',
            self::Refund => 'danger',
        };
    }
}
