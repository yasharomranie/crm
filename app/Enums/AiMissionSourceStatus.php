<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AiMissionSourceStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'در حال خواندن',
            self::Ready => 'آماده',
            self::Failed => 'ناموفق',
        };
    }
}
