<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The Eisenhower matrix quadrants used to prioritize tasks.
 */
enum TaskPriority: string implements HasColor, HasLabel
{
    case UrgentImportant = 'urgent_important';
    case NotUrgentImportant = 'not_urgent_important';
    case UrgentNotImportant = 'urgent_not_important';
    case Neither = 'neither';

    public function getLabel(): string
    {
        return match ($this) {
            self::UrgentImportant => 'فوری و مهم',
            self::NotUrgentImportant => 'غیرفوری و مهم',
            self::UrgentNotImportant => 'فوری و غیرمهم',
            self::Neither => 'غیرفوری و غیرمهم',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::UrgentImportant => 'danger',
            self::NotUrgentImportant => 'warning',
            self::UrgentNotImportant => 'info',
            self::Neither => 'gray',
        };
    }
}
