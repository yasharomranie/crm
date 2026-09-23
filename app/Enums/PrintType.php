<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PrintType: string implements HasLabel
{
    case Original = 'original';
    case Reprint = 'reprint';

    public function getLabel(): string
    {
        return match ($this) {
            self::Original => 'چاپ اصلی',
            self::Reprint => 'چاپ مجدد',
        };
    }
}
