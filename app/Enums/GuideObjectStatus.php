<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** How far an object is on its way to being recognisable. Only «Ready» is ever shown to visitors. */
enum GuideObjectStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
    case Disabled = 'disabled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'پیش‌نویس',
            self::Processing => 'در حال پردازش',
            self::Ready => 'آماده',
            self::Failed => 'ناموفق',
            self::Disabled => 'غیرفعال',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Processing => 'info',
            self::Ready => 'success',
            self::Failed => 'danger',
            self::Disabled => 'warning',
        };
    }
}
