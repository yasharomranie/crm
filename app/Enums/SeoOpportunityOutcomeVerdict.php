<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SeoOpportunityOutcomeVerdict: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Improved = 'improved';
    case Unchanged = 'unchanged';
    case Declined = 'declined';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'در انتظار نتیجه',
            self::Improved => 'بهبود یافت',
            self::Unchanged => 'بدون تغییر محسوس',
            self::Declined => 'بدتر شد',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Improved => 'success',
            self::Unchanged => 'info',
            self::Declined => 'danger',
        };
    }
}
