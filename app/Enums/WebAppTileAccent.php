<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum WebAppTileAccent: string implements HasColor, HasLabel
{
    case Orange = 'orange';
    case Green = 'green';

    public function getLabel(): string
    {
        return match ($this) {
            self::Orange => 'نارنجی',
            self::Green => 'سبز',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Orange => 'warning',
            self::Green => 'success',
        };
    }

    /**
     * The hex swatch this tile's icon background actually renders with on
     * the public app page - kept here (not just the Filament-facing
     * getColor() above) so the admin form's own preview and the public
     * page always agree on the same value instead of two hand-kept lists
     * drifting apart.
     */
    public function hex(): string
    {
        return match ($this) {
            self::Orange => '#F97316',
            self::Green => '#15803D',
        };
    }
}
