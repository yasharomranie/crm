<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TagVisibility: string implements HasLabel
{
    case System = 'system';
    case Personal = 'personal';

    public function getLabel(): string
    {
        return match ($this) {
            self::System => 'سازمانی',
            self::Personal => 'شخصی',
        };
    }
}
