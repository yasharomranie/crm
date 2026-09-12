<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ProjectPhaseType: string implements HasColor, HasLabel
{
    case Phase = 'phase';
    case Activity = 'activity';
    case Milestone = 'milestone';

    public function getLabel(): string
    {
        return match ($this) {
            self::Phase => 'فاز',
            self::Activity => 'فعالیت',
            self::Milestone => 'مایل‌استون',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Phase => 'primary',
            self::Activity => 'gray',
            self::Milestone => 'warning',
        };
    }
}
