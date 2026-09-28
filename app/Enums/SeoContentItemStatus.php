<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SeoContentItemStatus: string implements HasColor, HasLabel
{
    case Idea = 'idea';
    case Planned = 'planned';
    case Writing = 'writing';
    case Published = 'published';
    case NeedsUpdate = 'needs_update';

    public function getLabel(): string
    {
        return match ($this) {
            self::Idea => 'ایده',
            self::Planned => 'برنامه‌ریزی‌شده',
            self::Writing => 'در حال نگارش',
            self::Published => 'منتشرشده',
            self::NeedsUpdate => 'نیاز به بروزرسانی',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Idea => 'gray',
            self::Planned => 'info',
            self::Writing => 'warning',
            self::Published => 'success',
            self::NeedsUpdate => 'danger',
        };
    }
}
