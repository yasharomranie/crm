<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum WebhookEventType: string implements HasLabel
{
    case TicketScanned = 'ticket.scanned';
    case TicketRejected = 'ticket.rejected';
    case BookingCreated = 'booking.created';
    case BookingConfirmed = 'booking.confirmed';
    case BookingCheckedIn = 'booking.checked_in';
    case BookingCheckedOut = 'booking.checked_out';
    case BookingCancelled = 'booking.cancelled';
    case BookingPaymentRecorded = 'booking.payment_recorded';
    case DealStageChanged = 'deal.stage_changed';

    public function getLabel(): string
    {
        return match ($this) {
            self::TicketScanned => 'ورود موفق بلیت از گیت',
            self::TicketRejected => 'رد ورود بلیت از گیت',
            self::BookingCreated => 'ثبت رزرو جدید',
            self::BookingConfirmed => 'قطعی‌شدن رزرو',
            self::BookingCheckedIn => 'چک‌این مهمان',
            self::BookingCheckedOut => 'چک‌اوت مهمان',
            self::BookingCancelled => 'لغو رزرو یا عدم حضور',
            self::BookingPaymentRecorded => 'ثبت پرداخت یا استرداد رزرو',
            self::DealStageChanged => 'تغییر مرحله‌ی سرنخ',
        };
    }
}
