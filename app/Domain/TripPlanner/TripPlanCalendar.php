<?php

namespace App\Domain\TripPlanner;

use App\Models\TripPlan;
use App\Models\TripPlanItem;

/**
 * Renders a plan as an iCalendar (.ics) file the guest can open in any
 * calendar app: one event per stop, in Tehran local time.
 */
class TripPlanCalendar
{
    public function render(TripPlan $plan): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Ganjnameh//Trip Planner//FA',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-TIMEZONE:Asia/Tehran',
        ];

        /** @var TripPlanItem $item */
        foreach ($plan->items()->with('attraction')->get() as $item) {
            $date = $plan->dateForDay($item->day_number)->format('Ymd');
            $start = $date.'T'.str_replace(':', '', substr((string) $item->start_time, 0, 5)).'00';
            $end = $date.'T'.str_replace(':', '', substr((string) $item->end_time, 0, 5)).'00';

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = "UID:trip-plan-{$plan->id}-item-{$item->id}@ganjnameh";
            $lines[] = 'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z');
            $lines[] = "DTSTART;TZID=Asia/Tehran:{$start}";
            $lines[] = "DTEND;TZID=Asia/Tehran:{$end}";
            $lines[] = 'SUMMARY:'.$this->escape($item->attraction->name);

            if ($item->attraction->address) {
                $lines[] = 'LOCATION:'.$this->escape($item->attraction->address);
            }

            $description = trim(($item->note ?? '')."\n".$item->attraction->directionsUrl());
            $lines[] = 'DESCRIPTION:'.$this->escape($description);
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines)."\r\n";
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $text);
    }
}
