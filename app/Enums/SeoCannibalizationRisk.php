<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SeoCannibalizationRisk: string implements HasColor, HasLabel
{
    case None = 'none';
    case Possible = 'possible';
    case High = 'high';

    public function getLabel(): string
    {
        return match ($this) {
            self::None => 'بدون ریسک',
            self::Possible => 'ریسک احتمالی',
            self::High => 'ریسک بالا',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::None => 'gray',
            self::Possible => 'warning',
            self::High => 'danger',
        };
    }
}
