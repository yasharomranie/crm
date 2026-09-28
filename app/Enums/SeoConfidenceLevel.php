<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Every AI/rule-based finding in the SEO module carries one of these -
 * never presented as confirmed fact without enough data behind it. See
 * OpportunityEngine's own doc comments for how each detector assigns one.
 */
enum SeoConfidenceLevel: string implements HasColor, HasLabel
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public function getLabel(): string
    {
        return match ($this) {
            self::High => 'قطعیت بالا',
            self::Medium => 'قطعیت متوسط',
            self::Low => 'قطعیت پایین',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::High => 'success',
            self::Medium => 'warning',
            self::Low => 'gray',
        };
    }
}
