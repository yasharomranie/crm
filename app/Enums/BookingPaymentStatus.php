<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Where a booking's folio stands against what has been paid - worked out
 * from the booking (see Booking::paymentStatus()), never stored.
 */
enum BookingPaymentStatus: string implements HasColor, HasLabel
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';
    case Overpaid = 'overpaid';

    public function getLabel(): string
    {
        return match ($this) {
            self::Unpaid => 'پرداخت‌نشده',
            self::Partial => 'بخشی پرداخت شده',
            self::Paid => 'تسویه',
            self::Overpaid => 'بیش از صورت‌حساب',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Unpaid => 'danger',
            self::Partial => 'warning',
            self::Paid => 'success',
            self::Overpaid => 'info',
        };
    }
}
