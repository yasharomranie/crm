<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SeoKeywordTrend: string implements HasColor, HasLabel
{
    case Rising = 'rising';
    case Falling = 'falling';
    case Stable = 'stable';
    case New = 'new';

    public function getLabel(): string
    {
        return match ($this) {
            self::Rising => 'در حال رشد',
            self::Falling => 'در حال افت',
            self::Stable => 'ثابت',
            self::New => 'تازه‌ظاهرشده',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Rising => 'success',
            self::Falling => 'danger',
            self::Stable => 'gray',
            self::New => 'info',
        };
    }
}
