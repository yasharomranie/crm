<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum GateRejectionReason: string implements HasLabel
{
    case InvalidQr = 'invalid_qr';
    case Expired = 'expired';
    case AlreadyUsed = 'already_used';
    case NoRemainingUsage = 'no_remaining_usage';
    case WrongGate = 'wrong_gate';
    case WrongDirection = 'wrong_direction';
    case SequenceError = 'sequence_error';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case Blocked = 'blocked';
    case NotYetValid = 'not_yet_valid';

    public function getLabel(): string
    {
        return match ($this) {
            self::InvalidQr => 'کد QR نامعتبر است.',
            self::Expired => 'اعتبار این بلیت به پایان رسیده است.',
            self::AlreadyUsed => 'این بلیت قبلاً استفاده شده است.',
            self::NoRemainingUsage => 'ظرفیت مصرف این بلیت تمام شده است.',
            self::WrongGate => 'این بلیت برای این گیت مجاز نیست.',
            self::WrongDirection => 'جهت این بلیت با این گیت مطابقت ندارد.',
            self::SequenceError => 'ترتیب مصرف این بلیت رعایت نشده است (مثلاً برگشت قبل از رفت).',
            self::Cancelled => 'این بلیت لغو شده است.',
            self::Refunded => 'وجه این بلیت مسترد شده است.',
            self::Blocked => 'این بلیت مسدود شده است.',
            self::NotYetValid => 'اعتبار این بلیت هنوز آغاز نشده است.',
        };
    }
}
