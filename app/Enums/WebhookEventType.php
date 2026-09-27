<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum WebhookEventType: string implements HasLabel
{
    case TicketScanned = 'ticket.scanned';
    case TicketRejected = 'ticket.rejected';
    case BookingCreated = 'booking.created';
    case DealStageChanged = 'deal.stage_changed';

    public function getLabel(): string
    {
        return match ($this) {
            self::TicketScanned => 'ورود موفق بلیت از گیت',
            self::TicketRejected => 'رد ورود بلیت از گیت',
            self::BookingCreated => 'ثبت رزرو جدید',
            self::DealStageChanged => 'تغییر مرحله‌ی سرنخ',
        };
    }
}
