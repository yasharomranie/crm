<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum EventReminderUnit: string implements HasLabel
{
    case Minute = 'minute';
    case Hour = 'hour';
    case Day = 'day';
    case Week = 'week';

    public function getLabel(): string
    {
        return match ($this) {
            self::Minute => 'دقیقه',
            self::Hour => 'ساعت',
            self::Day => 'روز',
            self::Week => 'هفته',
        };
    }

    public function minutes(): int
    {
        return match ($this) {
            self::Minute => 1,
            self::Hour => 60,
            self::Day => 60 * 24,
            self::Week => 60 * 24 * 7,
        };
    }

    /**
     * The largest whole unit that divides evenly into $minutes - so 10080
     * minutes displays and edits as "1 هفته" rather than "10080 دقیقه", but
     * an odd value like 90 still round-trips exactly as "90 دقیقه" instead
     * of losing precision.
     *
     * @return array{value: int, unit: self}
     */
    public static function decompose(int $minutes): array
    {
        foreach ([self::Week, self::Day, self::Hour] as $unit) {
            if ($minutes > 0 && $minutes % $unit->minutes() === 0) {
                return ['value' => intdiv($minutes, $unit->minutes()), 'unit' => $unit];
            }
        }

        return ['value' => $minutes, 'unit' => self::Minute];
    }
}
