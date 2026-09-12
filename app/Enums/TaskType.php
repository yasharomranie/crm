<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TaskType: string implements HasLabel
{
    case Manual = 'manual';
    case FromForm = 'from_form';
    case ChecklistGenerated = 'checklist_generated';
    case FromBooking = 'from_booking';

    public function getLabel(): string
    {
        return match ($this) {
            self::Manual => 'دستی',
            self::FromForm => 'زاده‌شده از فرم',
            self::ChecklistGenerated => 'تولیدشده از چک‌لیست تکرارشونده',
            self::FromBooking => 'زاده‌شده از رزرو (نظافت پس از چک‌اوت)',
        };
    }
}
