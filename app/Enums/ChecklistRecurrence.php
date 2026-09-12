<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ChecklistRecurrence: string implements HasLabel
{
    case None = 'none';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case PerShift = 'per_shift';

    public function getLabel(): string
    {
        return match ($this) {
            self::None => 'یک‌باره',
            self::Daily => 'روزانه',
            self::Weekly => 'هفتگی',
            self::PerShift => 'هر شیفت',
        };
    }
}
