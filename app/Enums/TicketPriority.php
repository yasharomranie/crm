<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TicketPriority: string implements HasColor, HasLabel
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Critical = 'critical';

    public function getLabel(): string
    {
        return match ($this) {
            self::Low => 'کم',
            self::Normal => 'عادی',
            self::High => 'بالا',
            self::Critical => 'بحرانی',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Low => 'gray',
            self::Normal => 'info',
            self::High => 'warning',
            self::Critical => 'danger',
        };
    }

    /**
     * Default SLA response window in hours - used to set sla_due_at when a
     * ticket is created without one explicitly.
     */
    public function slaHours(): int
    {
        return match ($this) {
            self::Low => 72,
            self::Normal => 48,
            self::High => 24,
            self::Critical => 4,
        };
    }
}
