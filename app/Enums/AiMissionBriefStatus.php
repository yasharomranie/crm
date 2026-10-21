<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AiMissionBriefStatus: string implements HasLabel
{
    case None = 'none';
    case Building = 'building';
    case Review = 'review';
    case Approved = 'approved';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::None => 'ساخته نشده',
            self::Building => 'در حال ساخت',
            self::Review => 'منتظر تأیید شما',
            self::Approved => 'تأییدشده',
            self::Failed => 'ساخت ناموفق',
        };
    }
}
