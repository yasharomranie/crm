<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TicketDirection: string implements HasLabel
{
    case Outbound = 'outbound';
    case Return = 'return';

    /**
     * A variant/gate with no direction restriction (e.g. a single-entry
     * aquarium ticket, or a gate that accepts both) - null direction
     * everywhere means "not applicable", not "either".
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Outbound => 'رفت',
            self::Return => 'برگشت',
        };
    }
}
