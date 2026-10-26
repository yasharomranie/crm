<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DigitalProjectActionStatus: string implements HasColor, HasLabel
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Done = 'done';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'باز',
            self::InProgress => 'در حال انجام',
            self::Done => 'انجام‌شده',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::InProgress => 'info',
            self::Done => 'success',
        };
    }

    /**
     * @return list<string>
     */
    public static function unfinishedValues(): array
    {
        return [self::Open->value, self::InProgress->value];
    }
}
