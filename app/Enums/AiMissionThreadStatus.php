<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AiMissionThreadStatus: string implements HasLabel
{
    case Idle = 'idle';
    case Running = 'running';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Idle => 'آماده',
            self::Running => 'تیم در حال گفت‌وگوست',
            self::Failed => 'نیمه‌کاره ماند',
        };
    }
}
