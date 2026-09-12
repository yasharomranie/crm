<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PriceAdjustmentType: string implements HasLabel
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Percentage => 'درصدی',
            self::Fixed => 'مبلغ ثابت',
        };
    }
}
