<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum DigitalProjectStatus: string implements HasColor, HasLabel
{
    case Planning = 'planning';
    case Active = 'active';
    case NeedsAttention = 'needs_attention';
    case Paused = 'paused';
    case Done = 'done';

    public function getLabel(): string
    {
        return match ($this) {
            self::Planning => 'برنامه‌ریزی',
            self::Active => 'فعال',
            self::NeedsAttention => 'نیازمند توجه',
            self::Paused => 'متوقف',
            self::Done => 'تمام‌شده',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Planning => 'info',
            self::Active => 'success',
            self::NeedsAttention => 'warning',
            self::Paused => 'gray',
            self::Done => 'primary',
        };
    }

    /** Whether the project is still being worked on (a finished or paused one never counts as overdue). */
    public function isOpen(): bool
    {
        return in_array($this, [self::Planning, self::Active, self::NeedsAttention], true);
    }

    /**
     * @return list<string>
     */
    public static function openValues(): array
    {
        return collect(self::cases())->filter(fn (self $status) => $status->isOpen())->map(fn (self $status) => $status->value)->values()->all();
    }
}
