<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SeoKeywordSuggestionStatus: string implements HasColor, HasLabel
{
    case New = 'new';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'جدید',
            self::Approved => 'تأییدشده',
            self::Rejected => 'رد‌شده',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'gray',
            self::Approved => 'success',
            self::Rejected => 'danger',
        };
    }
}
