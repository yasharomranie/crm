<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AiMissionActionStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Executed = 'executed';
    case Rejected = 'rejected';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'منتظر تأیید',
            self::Executed => 'انجام شد',
            self::Rejected => 'رد شد',
            self::Failed => 'انجام نشد',
        };
    }
}
