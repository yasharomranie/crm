<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DigitalProjectLogKind: string implements HasColor, HasLabel
{
    case Done = 'done';
    case Problem = 'problem';
    case Idea = 'idea';

    public function getLabel(): string
    {
        return match ($this) {
            self::Done => 'کار انجام‌شده',
            self::Problem => 'مشکل',
            self::Idea => 'ایده',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Done => 'success',
            self::Problem => 'danger',
            self::Idea => 'info',
        };
    }
}
