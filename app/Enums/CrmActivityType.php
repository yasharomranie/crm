<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum CrmActivityType: string implements HasColor, HasIcon, HasLabel
{
    case Call = 'call';
    case Note = 'note';
    case Meeting = 'meeting';

    public function getLabel(): string
    {
        return match ($this) {
            self::Call => 'تماس تلفنی',
            self::Note => 'یادداشت',
            self::Meeting => 'جلسه',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Call => 'info',
            self::Note => 'gray',
            self::Meeting => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Call => 'heroicon-o-phone',
            self::Note => 'heroicon-o-pencil-square',
            self::Meeting => 'heroicon-o-user-group',
        };
    }
}
