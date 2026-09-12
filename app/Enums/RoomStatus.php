<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum RoomStatus: string implements HasColor, HasLabel
{
    case Available = 'available';
    case Occupied = 'occupied';
    case NeedsCleaning = 'needs_cleaning';
    case OutOfService = 'out_of_service';

    public function getLabel(): string
    {
        return match ($this) {
            self::Available => 'خالی و آماده',
            self::Occupied => 'اشغال (مهمان مقیم)',
            self::NeedsCleaning => 'نیازمند نظافت',
            self::OutOfService => 'خارج از سرویس',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Available => 'success',
            self::Occupied => 'info',
            self::NeedsCleaning => 'warning',
            self::OutOfService => 'danger',
        };
    }
}
