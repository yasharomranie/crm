<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum MessageChannel: string implements HasColor, HasIcon, HasLabel
{
    case Email = 'email';
    case Sms = 'sms';
    case WhatsApp = 'whatsapp';

    public function getLabel(): string
    {
        return match ($this) {
            self::Email => 'ایمیل',
            self::Sms => 'پیامک',
            self::WhatsApp => 'واتس‌اپ',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Email => 'info',
            self::Sms => 'warning',
            self::WhatsApp => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Email => 'heroicon-o-envelope',
            self::Sms => 'heroicon-o-device-phone-mobile',
            self::WhatsApp => 'heroicon-o-chat-bubble-left-right',
        };
    }
}
