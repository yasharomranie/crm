<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum GateScanResult: string implements HasColor, HasLabel
{
    case Success = 'success';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::Success => 'موفق',
            self::Rejected => 'رد شد',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Success => 'success',
            self::Rejected => 'danger',
        };
    }
}
