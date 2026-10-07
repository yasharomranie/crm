<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AttractionCategory: string implements HasColor, HasLabel
{
    case Nature = 'nature';
    case Historical = 'historical';
    case Adventure = 'adventure';
    case Shopping = 'shopping';
    case Food = 'food';
    case Religious = 'religious';
    case Family = 'family';

    public function getLabel(): string
    {
        return match ($this) {
            self::Nature => 'طبیعت‌گردی',
            self::Historical => 'تاریخی',
            self::Adventure => 'ماجراجویی',
            self::Shopping => 'خرید',
            self::Food => 'خوراک و رستوران',
            self::Religious => 'مذهبی',
            self::Family => 'خانوادگی',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Nature => 'success',
            self::Historical => 'warning',
            self::Adventure => 'danger',
            self::Shopping => 'info',
            self::Food => 'gray',
            self::Religious => 'primary',
            self::Family => 'success',
        };
    }
}
