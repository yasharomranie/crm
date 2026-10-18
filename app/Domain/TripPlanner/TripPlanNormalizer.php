<?php

namespace App\Domain\TripPlanner;

use App\Models\Attraction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Turns whatever a planner produced (the AI's JSON, the rule-based
 * planner's draft, or a day after the guest edited it) into a schedule
 * that can actually be followed: stops sit inside the place's opening
 * hours, on days it is open, never overlap, leave real travel time between
 * them, never repeat a place, and respect the pace's stops-per-day limit.
 * Nothing here trusts the model - the prompt only asks for these things,
 * this enforces them.
 */
class TripPlanNormalizer
{
    private const DAY_START = 7 * 60;

    private const DAY_END = 22 * 60;

    private const MIN_STOP_MINUTES = 15;

    private const TRAVEL_BUFFER_MINUTES = 10;

    /**
     * @param  array<int, mixed>  $days  [['day_number' => 1, 'items' => [['attraction_id', 'start_time', 'end_time', 'note'], ...]], ...]
     * @param  Collection<int, Attraction>  $attractions  keyed by id
     * @param  list<int>  $alreadyUsed  attraction ids already placed on days not in $days
     * @return array<int, list<array{attraction_id: int, start_time: string, end_time: string, note: ?string, travel_minutes: ?int}>> keyed by day number
     */
    public function normalize(
        array $days,
        Collection $attractions,
        Carbon $startsOn,
        int $dayCount,
        string $pace,
        bool $hasCar,
        bool $keepOrder = false,
        array $alreadyUsed = [],
    ): array {
        $maxStops = self::maxStopsFor($pace);
        $used = array_flip($alreadyUsed);
        $result = [];

        foreach ($days as $day) {
            $dayNumber = is_array($day) ? (int) ($day['day_number'] ?? 0) : 0;

            if ($dayNumber < 1 || $dayNumber > $dayCount || ! is_array($day['items'] ?? null)) {
                continue;
            }

            $date = $startsOn->copy()->addDays($dayNumber - 1);
            $candidates = [];

            foreach ($day['items'] as $order => $item) {
                $attraction = is_array($item) && is_numeric($item['attraction_id'] ?? null)
                    ? $attractions->get((int) $item['attraction_id'])
                    : null;

                if (! $attraction || isset($used[$attraction->id]) || $attraction->isClosedOnDate($date)) {
                    continue;
                }

                $start = self::toMinutes($item['start_time'] ?? null);

                if ($start === null) {
                    continue;
                }

                $end = self::toMinutes($item['end_time'] ?? null);
                $duration = $end !== null && $end > $start ? $end - $start : (int) $attraction->avg_visit_minutes;

                $candidates[] = [
                    'attraction' => $attraction,
                    'start' => $start,
                    'duration' => max(self::MIN_STOP_MINUTES, $duration),
                    'note' => $item['note'] ?? null,
                    'order' => $order,
                ];
            }

            if (! $keepOrder) {
                usort($candidates, fn (array $a, array $b) => [$a['start'], $a['order']] <=> [$b['start'], $b['order']]);
            }

            $scheduled = [];
            $previous = null;

            foreach ($candidates as $candidate) {
                if (count($scheduled) >= $maxStops) {
                    break;
                }

                /** @var Attraction $attraction */
                $attraction = $candidate['attraction'];
                $opensAt = self::toMinutes($attraction->opens_at);
                $closesAt = self::toMinutes($attraction->closes_at);

                $start = max($candidate['start'], self::DAY_START, $opensAt ?? 0);
                $travel = null;

                if ($previous) {
                    $travel = self::travelMinutes($previous['attraction'], $attraction, $hasCar);
                    $start = max($start, $previous['end'] + $travel);
                }

                $limit = min($closesAt ?? self::DAY_END, self::DAY_END);
                $end = min($start + $candidate['duration'], $limit);

                if ($end - $start < self::MIN_STOP_MINUTES) {
                    continue;
                }

                $scheduled[] = [
                    'attraction_id' => $attraction->id,
                    'start_time' => self::format($start),
                    'end_time' => self::format($end),
                    'note' => filled($candidate['note']) ? (string) $candidate['note'] : null,
                    'travel_minutes' => $travel,
                ];
                $used[$attraction->id] = true;
                $previous = ['attraction' => $attraction, 'end' => $end];
            }

            $result[$dayNumber] = $scheduled;
        }

        return $result;
    }

    public static function maxStopsFor(string $pace): int
    {
        return match ($pace) {
            'relaxed' => 2,
            'packed' => 4,
            default => 3,
        };
    }

    /**
     * Rough door-to-door minutes between two places: straight-line
     * distance at a city speed (slower without a car) plus a fixed buffer
     * for parking/walking, bounded to something believable. Places with no
     * coordinates get a flat guess.
     */
    public static function travelMinutes(Attraction $from, Attraction $to, bool $hasCar): int
    {
        $km = self::distanceKm($from, $to);

        if ($km === null) {
            return $hasCar ? 20 : 30;
        }

        $speed = $hasCar ? 30 : 15;

        return (int) max(15, min(90, ceil($km / $speed * 60) + self::TRAVEL_BUFFER_MINUTES));
    }

    public static function distanceKm(Attraction $a, Attraction $b): ?float
    {
        if (blank($a->lat) || blank($a->lng) || blank($b->lat) || blank($b->lng)) {
            return null;
        }

        $lat1 = deg2rad((float) $a->lat);
        $lat2 = deg2rad((float) $b->lat);
        $dLat = $lat2 - $lat1;
        $dLng = deg2rad((float) $b->lng - (float) $a->lng);

        $h = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;

        return 6371 * 2 * asin(min(1, sqrt($h)));
    }

    public static function toMinutes(mixed $time): ?int
    {
        if (! is_string($time) || ! preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', trim($time), $matches)) {
            return null;
        }

        [$hours, $minutes] = [(int) $matches[1], (int) $matches[2]];

        return $hours > 23 || $minutes > 59 ? null : $hours * 60 + $minutes;
    }

    public static function format(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
