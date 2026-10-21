<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AiMissionRoleType: string implements HasLabel
{
    case Coordinator = 'coordinator';
    case Specialist = 'specialist';
    case Critic = 'critic';

    public function getLabel(): string
    {
        return match ($this) {
            self::Coordinator => 'هماهنگ‌کننده',
            self::Specialist => 'متخصص',
            self::Critic => 'منتقد',
        };
    }
}
