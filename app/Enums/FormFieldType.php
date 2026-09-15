<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FormFieldType: string implements HasLabel
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case DateJalali = 'date_jalali';
    case Select = 'select';
    case Radio = 'radio';
    case Checkbox = 'checkbox';
    case File = 'file';
    case Signature = 'signature';
    case Gps = 'gps';
    case Table = 'table';

    public function getLabel(): string
    {
        return match ($this) {
            self::Text => 'متن کوتاه',
            self::Textarea => 'متن بلند',
            self::Number => 'عدد',
            self::DateJalali => 'تاریخ شمسی',
            self::Select => 'لیست کشویی',
            self::Radio => 'انتخاب تکی',
            self::Checkbox => 'چک‌باکس',
            self::File => 'فایل پیوست',
            self::Signature => 'امضای دیجیتال',
            self::Gps => 'موقعیت مکانی (GPS)',
            self::Table => 'جدول (ردیف‌های تکرارشونده)',
        };
    }

    /**
     * Field types whose value is stored in form_submission_values.value_json
     * instead of a scalar column.
     */
    public function storesAsJson(): bool
    {
        return match ($this) {
            self::Checkbox, self::Gps, self::Table => true,
            default => false,
        };
    }
}
