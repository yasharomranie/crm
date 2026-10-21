<?php

namespace App\Domain\Ai\Missions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

/**
 * When a recurring mission runs next. Times are the manager's own wall clock
 * (Asia/Tehran, like every other schedule in the app) and the result is
 * returned in that zone; the column stores it as UTC.
 */
class MissionSchedule
{
    public const TIMEZONE = 'Asia/Tehran';

    public const FREQUENCIES = ['daily' => 'هر روز', 'weekly' => 'هر هفته', 'monthly' => 'هر ماه'];

    /** Carbon's day numbers (0 = Sunday) in the order a Persian week reads them. */
    public const WEEKDAYS = [6 => 'شنبه', 0 => 'یکشنبه', 1 => 'دوشنبه', 2 => 'سه‌شنبه', 3 => 'چهارشنبه', 4 => 'پنجشنبه', 5 => 'جمعه'];

    /**
     * The first run strictly after $from.
     */
    public function nextRun(string $frequency, string $time, ?int $weekday = null, ?int $day = null, ?Carbon $from = null): Carbon
    {
        $from = ($from ?? Date::now())->copy()->timezone(self::TIMEZONE);
        [$hour, $minute] = array_map('intval', explode(':', $time) + [0, 0]);
        $candidate = $from->copy()->setTime($hour, $minute, 0);

        return match ($frequency) {
            'weekly' => $this->advanceToWeekday($candidate, $weekday ?? 6, $from),
            'monthly' => $this->advanceToDay($candidate, $day ?? 1, $from),
            default => $candidate->lessThanOrEqualTo($from) ? $candidate->addDay() : $candidate,
        };
    }

    private function advanceToWeekday(Carbon $candidate, int $weekday, Carbon $from): Carbon
    {
        while ($candidate->dayOfWeek !== $weekday || $candidate->lessThanOrEqualTo($from)) {
            $candidate->addDay();
        }

        return $candidate;
    }

    private function advanceToDay(Carbon $candidate, int $day, Carbon $from): Carbon
    {
        $candidate->day(min($day, 28));

        if ($candidate->lessThanOrEqualTo($from)) {
            $candidate->addMonthNoOverflow()->day(min($day, 28));
        }

        return $candidate;
    }
}
