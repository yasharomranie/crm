<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The type of a single column inside a FormFieldType::Table field's
 * `options.columns` definition - a deliberately small subset of
 * FormFieldType, since a repeatable table row is meant for compact,
 * scannable data entry (names, amounts, dates), not a file upload or
 * signature pad nested inside every row.
 */
enum TableColumnType: string implements HasLabel
{
    case Text = 'text';
    case Number = 'number';
    case DateJalali = 'date_jalali';

    public function getLabel(): string
    {
        return match ($this) {
            self::Text => 'متن',
            self::Number => 'عدد',
            self::DateJalali => 'تاریخ شمسی',
        };
    }
}
