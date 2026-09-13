<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ConversationType: string implements HasIcon, HasLabel
{
    case Direct = 'direct';
    case Group = 'group';

    public function getLabel(): string
    {
        return match ($this) {
            self::Direct => 'گفتگوی دو نفره',
            self::Group => 'گروه',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Direct => 'heroicon-o-user',
            self::Group => 'heroicon-o-user-group',
        };
    }
}
