<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TaskStatus: string implements HasColor, HasLabel
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Todo => 'در صف انجام',
            self::InProgress => 'در حال انجام',
            self::Done => 'تکمیل‌شده',
            self::Overdue => 'معوقه',
            self::Cancelled => 'لغو شده',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Todo => 'gray',
            self::InProgress => 'info',
            self::Done => 'success',
            self::Overdue => 'danger',
            self::Cancelled => 'gray',
        };
    }
}
