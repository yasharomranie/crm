<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AiMissionCardStatus: string implements HasLabel
{
    case Open = 'open';
    case Rejected = 'rejected';
    case Locked = 'locked';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'باز',
            self::Rejected => 'رد شده',
            self::Locked => 'تأییدشده',
        };
    }
}
