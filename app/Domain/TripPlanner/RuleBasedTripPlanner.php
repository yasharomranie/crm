<?php

namespace App\Domain\TripPlanner;

use App\Models\Attraction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A planner that needs no AI: scores the places for this guest (interests,
 * admin boost, group, budget), then fills each day with nearby ones.
 * It is the safety net when the AI provider is missing, down or returns
 * something unusable, so the trip planner never just fails - and it is
 * free to run. Its draft goes through TripPlanNormalizer like any other.
 */
class RuleBasedTripPlanner
{
    /**
     * @param  Collection<int, Attraction>  $attractions
     * @param  array{interests?: list<string>, pace?: string, group_type?: ?string, budget_level?: ?string}  $input
     * @return array{overview: string, days: list<array{day_number: int, items: list<array<string, mixed>>}>}
     */
    public function plan(Collection $attractions, Carbon $startsOn, int $days, array $input): array
    {
        $perDay = TripPlanNormalizer::maxStopsFor($input['pace'] ?? 'balanced');
        $remaining = $attractions->sortBy('id')->sortByDesc(fn (Attraction $a) => $this->score($a, $input))->values();
        $planDays = [];
        $startMinutes = ($input['pace'] ?? 'balanced') === 'relaxed' ? 10 * 60 : 9 * 60;

        for ($dayNumber = 1; $dayNumber <= $days; $dayNumber++) {
            $date = $startsOn->copy()->addDays($dayNumber - 1);
            $pool = $remaining->reject(fn (Attraction $a) => $a->isClosedOnDate($date))->values();

            if ($pool->isEmpty()) {
                $planDays[] = ['day_number' => $dayNumber, 'items' => []];

                continue;
            }

            $chosen = collect([$pool->first()]);
            $topPool = $pool->slice(1, $perDay * 2)->values();

            while ($chosen->count() < $perDay && $topPool->isNotEmpty()) {
                $last = $chosen->last();
                $next = $topPool->sortBy(fn (Attraction $a) => TripPlanNormalizer::distanceKm($last, $a) ?? 999)->first();
                $chosen->push($next);
                $topPool = $topPool->reject(fn (Attraction $a) => $a->id === $next->id)->values();
            }

            $remaining = $remaining->reject(fn (Attraction $a) => $chosen->contains('id', $a->id))->values();

            $cursor = $startMinutes;
            $items = [];

            foreach ($chosen as $attraction) {
                $items[] = [
                    'attraction_id' => $attraction->id,
                    'start_time' => TripPlanNormalizer::format($cursor),
                    'end_time' => TripPlanNormalizer::format($cursor + (int) $attraction->avg_visit_minutes),
                    'note' => $attraction->best_time_note ?: null,
                ];
                $cursor += (int) $attraction->avg_visit_minutes + 30;
            }

            $planDays[] = ['day_number' => $dayNumber, 'items' => $items];
        }

        return [
            'overview' => "برنامه‌ی پیشنهادی {$days} روزه‌ی همدان، بر اساس علایق شما و نزدیکی مکان‌ها به یکدیگر چیده شده است.",
            'days' => $planDays,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function score(Attraction $attraction, array $input): int
    {
        $score = (int) $attraction->boost_weight * 10;

        if (in_array($attraction->category->value, $input['interests'] ?? [], true)) {
            $score += 30;
        }

        $score += match ($input['group_type'] ?? null) {
            'family_kids' => $attraction->kid_friendly === true ? 15 : ($attraction->kid_friendly === false ? -20 : 0),
            'seniors' => $attraction->senior_friendly === true ? 15 : ($attraction->senior_friendly === false ? -20 : 0),
            default => 0,
        };

        if (($input['budget_level'] ?? null) === 'low') {
            $score += match (true) {
                blank($attraction->entry_fee) || $attraction->entry_fee === 0 => 10,
                $attraction->entry_fee > 100_000 => -15,
                default => 0,
            };
        }

        return $score;
    }
}
