<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AiMissionSourceKind: string implements HasLabel
{
    case Policy = 'policy';
    case Evidence = 'evidence';
    case Background = 'background';

    public function getLabel(): string
    {
        return match ($this) {
            self::Policy => 'استراتژی و سیاست (الزام‌آور)',
            self::Evidence => 'داده و گزارش (شاهد)',
            self::Background => 'پس‌زمینه (مرجع)',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Policy => 'الزام‌آور',
            self::Evidence => 'شاهد',
            self::Background => 'مرجع',
        };
    }
}
