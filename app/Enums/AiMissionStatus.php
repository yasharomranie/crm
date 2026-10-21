<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AiMissionStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Running = 'running';
    case WaitingApproval = 'waiting_approval';
    case Done = 'done';
    case Failed = 'failed';
    case Stopped = 'stopped';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'پیش‌نویس',
            self::Running => 'در حال اجرا',
            self::WaitingApproval => 'منتظر تأیید شما',
            self::Done => 'تمام شد',
            self::Failed => 'ناموفق',
            self::Stopped => 'متوقف شد',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Running => 'info',
            self::WaitingApproval => 'warning',
            self::Done => 'success',
            self::Failed => 'danger',
            self::Stopped => 'gray',
        };
    }
}
