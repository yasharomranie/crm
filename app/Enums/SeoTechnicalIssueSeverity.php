<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SeoTechnicalIssueSeverity: string implements HasColor, HasLabel
{
    case Critical = 'critical';
    case Warning = 'warning';
    case Info = 'info';

    public function getLabel(): string
    {
        return match ($this) {
            self::Critical => 'بحرانی',
            self::Warning => 'هشدار',
            self::Info => 'اطلاعاتی',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Critical => 'danger',
            self::Warning => 'warning',
            self::Info => 'gray',
        };
    }
}
