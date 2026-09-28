<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SeoTechnicalAuditStatus: string implements HasColor, HasLabel
{
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Running => 'در حال اجرا',
            self::Completed => 'تکمیل‌شده',
            self::Failed => 'ناموفق',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Running => 'info',
            self::Completed => 'success',
            self::Failed => 'danger',
        };
    }
}
