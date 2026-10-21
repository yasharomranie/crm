<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AiMissionCardKind: string implements HasLabel
{
    case Finding = 'finding';
    case Critique = 'critique';
    case Summary = 'summary';

    public function getLabel(): string
    {
        return match ($this) {
            self::Finding => 'یافته',
            self::Critique => 'نقد',
            self::Summary => 'جمع‌بندی',
        };
    }
}
