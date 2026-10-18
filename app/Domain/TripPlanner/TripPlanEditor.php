<?php

namespace App\Domain\TripPlanner;

use App\Models\Attraction;
use App\Models\TripPlan;
use App\Models\TripPlanItem;
use Illuminate\Support\Facades\DB;

/**
 * The edits a guest can make to a plan they already have. Every edit ends
 * by re-checking the whole day through TripPlanNormalizer, so a hand-made
 * plan keeps the same guarantees as a generated one - and an edit that
 * would break them (a stop pushed past closing time, a place closed that
 * day) is refused with a reason instead of silently producing nonsense.
 */
class TripPlanEditor
{
    private const MAX_STOPS_PER_DAY = 4;

    public function __construct(private readonly TripPlanNormalizer $normalizer) {}

    public function removeItem(TripPlan $plan, TripPlanItem $item): void
    {
        $dayNumber = $item->day_number;

        DB::transaction(function () use ($plan, $item, $dayNumber) {
            $item->delete();
            $this->resequence($plan, $dayNumber, compact: false);
        });
    }

    public function addItem(TripPlan $plan, int $attractionId, int $dayNumber): TripPlanItem
    {
        if ($dayNumber < 1 || $dayNumber > $plan->days) {
            throw TripPlannerException::invalidEdit('این روز در برنامه‌ی شما وجود ندارد.');
        }

        $attraction = Attraction::query()->where('is_active', true)->find($attractionId);

        if (! $attraction) {
            throw TripPlannerException::invalidEdit('این مکان در دسترس نیست.');
        }

        if ($plan->items()->where('attraction_id', $attraction->id)->exists()) {
            throw TripPlannerException::invalidEdit('این مکان قبلاً در برنامه‌ی شما هست.');
        }

        if ($plan->items()->where('day_number', $dayNumber)->count() >= self::MAX_STOPS_PER_DAY) {
            throw TripPlannerException::invalidEdit('در هر روز حداکثر '.self::MAX_STOPS_PER_DAY.' توقف می‌تواند باشد.');
        }

        if ($attraction->isClosedOnDate($plan->dateForDay($dayNumber))) {
            throw TripPlannerException::invalidEdit('این مکان در آن روز تعطیل است.');
        }

        return DB::transaction(function () use ($plan, $attraction, $dayNumber) {
            $item = $plan->items()->create([
                'attraction_id' => $attraction->id,
                'day_number' => $dayNumber,
                'start_time' => '00:00',
                'end_time' => '00:00',
                'sort_order' => ((int) $plan->items()->where('day_number', $dayNumber)->max('sort_order')) + 1,
            ]);

            // Hint the day's first free time; the normalizer places it
            // after the previous stop plus travel and inside opening hours.
            $this->resequence($plan, $dayNumber, compact: false, durations: [$item->id => (int) $attraction->avg_visit_minutes], hints: [$item->id => '09:00']);

            return $item->fresh();
        });
    }

    public function moveItem(TripPlan $plan, TripPlanItem $item, string $direction): void
    {
        $items = $plan->items()->where('day_number', $item->day_number)->orderBy('sort_order')->orderBy('id')->get()->values();
        $index = $items->search(fn (TripPlanItem $candidate) => $candidate->id === $item->id);
        $swapWith = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index === false || $swapWith < 0 || $swapWith >= $items->count()) {
            return;
        }

        DB::transaction(function () use ($plan, $items, $index, $swapWith, $item) {
            $other = $items[$swapWith];
            [$a, $b] = [$items[$index]->sort_order, $other->sort_order];

            // Equal sort orders would make the swap a no-op, so renumber.
            if ($a === $b) {
                foreach ($items as $position => $candidate) {
                    $candidate->update(['sort_order' => $position]);
                }
                [$a, $b] = [$index, $swapWith];
            }

            $item->update(['sort_order' => $b]);
            $other->update(['sort_order' => $a]);

            $this->resequence($plan, $item->day_number, compact: true);
        });
    }

    /**
     * Re-times a day in its stored order. With $compact stops are packed as
     * early as the previous stop, travel and opening hours allow (used
     * after a reorder); without it each stop keeps its own start time
     * unless it must move later.
     *
     * @param  array<int, int>  $durations  item id => minutes, for items without a real duration yet
     * @param  array<int, string>  $hints  item id => preferred start (HH:MM)
     */
    private function resequence(TripPlan $plan, int $dayNumber, bool $compact, array $durations = [], array $hints = []): void
    {
        $items = $plan->items()->where('day_number', $dayNumber)->orderBy('sort_order')->orderBy('id')->get();

        if ($items->isEmpty()) {
            return;
        }

        $firstStart = $items->map(fn (TripPlanItem $i) => TripPlanNormalizer::toMinutes($i->start_time) ?? 0)->filter()->min();
        $payload = [];

        foreach ($items->values() as $position => $item) {
            $start = TripPlanNormalizer::toMinutes($item->start_time);
            $end = TripPlanNormalizer::toMinutes($item->end_time);
            $duration = $durations[$item->id] ?? (($start !== null && $end !== null && $end > $start) ? $end - $start : (int) $item->attraction->avg_visit_minutes);

            $startHint = $hints[$item->id]
                ?? ($compact ? ($position === 0 ? TripPlanNormalizer::format($firstStart ?: 540) : '00:00') : substr((string) $item->start_time, 0, 5));

            $payload[] = [
                'attraction_id' => $item->attraction_id,
                'start_time' => $startHint,
                'end_time' => TripPlanNormalizer::format(min(23 * 60 + 59, (TripPlanNormalizer::toMinutes($startHint) ?? 0) + $duration)),
                'note' => $item->note,
            ];
        }

        $scheduled = $this->normalizer->normalize(
            [['day_number' => $dayNumber, 'items' => $payload]],
            $items->pluck('attraction')->keyBy('id'),
            ($plan->starts_on ?? $plan->created_at)->copy()->startOfDay(),
            $plan->days,
            'packed',
            $plan->has_car,
            keepOrder: true,
        )[$dayNumber] ?? [];

        if (count($scheduled) !== $items->count()) {
            throw TripPlannerException::invalidEdit('با این تغییر یکی از توقف‌ها از ساعت کاری یا زمان همان روز بیرون می‌افتد.');
        }

        foreach ($items->values() as $position => $item) {
            $item->update([
                'start_time' => $scheduled[$position]['start_time'],
                'end_time' => $scheduled[$position]['end_time'],
                'travel_minutes' => $scheduled[$position]['travel_minutes'],
                'sort_order' => $position,
            ]);
        }
    }
}
