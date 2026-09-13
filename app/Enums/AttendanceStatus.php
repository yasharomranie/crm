<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AttendanceStatus: string implements HasColor, HasLabel
{
    case Incomplete = 'incomplete';
    case OnTime = 'on_time';
    case Late = 'late';
    case Absent = 'absent';

    public function getLabel(): string
    {
        return match ($this) {
            self::Incomplete => 'ورود ثبت شده (در انتظار خروج)',
            self::OnTime => 'به‌موقع',
            self::Late => 'با تأخیر',
            self::Absent => 'غایب',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Incomplete => 'info',
            self::OnTime => 'success',
            self::Late => 'warning',
            self::Absent => 'danger',
        };
    }
}
