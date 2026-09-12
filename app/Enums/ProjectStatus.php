<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ProjectStatus: string implements HasColor, HasLabel
{
    case Planning = 'planning';
    case InProgress = 'in_progress';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Planning => 'در حال برنامه‌ریزی',
            self::InProgress => 'در حال اجرا',
            self::OnHold => 'متوقف‌شده',
            self::Completed => 'تکمیل‌شده',
            self::Cancelled => 'لغو شده',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Planning => 'gray',
            self::InProgress => 'info',
            self::OnHold => 'warning',
            self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }
}
