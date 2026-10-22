<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AiMissionMessageKind: string implements HasLabel
{
    case User = 'user';
    case Plan = 'plan';
    case Answer = 'answer';
    case Critique = 'critique';
    case Final = 'final';

    public function getLabel(): string
    {
        return match ($this) {
            self::User => 'شما',
            self::Plan => 'برنامه',
            self::Answer => 'نظر',
            self::Critique => 'نقد',
            self::Final => 'جواب نهایی',
        };
    }
}
