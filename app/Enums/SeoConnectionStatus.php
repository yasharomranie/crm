<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SeoConnectionStatus: string implements HasColor, HasLabel
{
    case NotConnected = 'not_connected';
    case Connected = 'connected';
    case TokenExpired = 'token_expired';
    case Error = 'error';

    public function getLabel(): string
    {
        return match ($this) {
            self::NotConnected => 'متصل نشده',
            self::Connected => 'متصل',
            self::TokenExpired => 'نیاز به اتصال مجدد',
            self::Error => 'خطا',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::NotConnected => 'gray',
            self::Connected => 'success',
            self::TokenExpired => 'warning',
            self::Error => 'danger',
        };
    }
}
