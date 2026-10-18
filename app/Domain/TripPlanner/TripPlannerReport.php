<?php

namespace App\Domain\TripPlanner;

use App\Enums\AttractionCategory;
use App\Enums\BookingStatus;
use App\Models\Attraction;
use App\Models\Booking;
use App\Models\TripPlan;
use App\Models\TripPlanItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Numbers for the manager: how much the trip planner is used, what it
 * schedules (and what it never does), how good guests think the plans are,
 * how often the AI had to be replaced by the rule-based planner, and how
 * many plans turned into a booking for the planned dates.
 */
class TripPlannerReport
{
    /**
     * @return array{
     *     days: int, plans: int, average_days: float, rated_plans: int, average_rating: ?float,
     *     fallback_plans: int, average_generation_ms: ?int, shared_plans: int,
     *     plans_with_booking: int, booking_conversion_percent: ?float,
     *     top_attractions: list<array{name: string, count: int}>,
     *     never_scheduled: list<string>,
     *     categories: list<array{category: string, count: int}>
     * }
     */
    public function build(int $days = 30): array
    {
        $days = max(1, min(365, $days));
        $since = now()->subDays($days);

        $plans = TripPlan::query()->where('created_at', '>=', $since)->get();
        $rated = $plans->whereNotNull('rating');

        $topAttractions = TripPlanItem::query()
            ->join('trip_plans', 'trip_plans.id', '=', 'trip_plan_items.trip_plan_id')
            ->join('attractions', 'attractions.id', '=', 'trip_plan_items.attraction_id')
            ->where('trip_plans.created_at', '>=', $since)
            ->groupBy('attractions.id', 'attractions.name')
            ->select('attractions.name', DB::raw('count(*) as stop_count'))
            ->orderByDesc('stop_count')
            ->limit(10)
            ->get()
            ->map(fn ($row) => ['name' => $row->name, 'count' => (int) $row->stop_count])
            ->all();

        $categories = TripPlanItem::query()
            ->join('trip_plans', 'trip_plans.id', '=', 'trip_plan_items.trip_plan_id')
            ->join('attractions', 'attractions.id', '=', 'trip_plan_items.attraction_id')
            ->where('trip_plans.created_at', '>=', $since)
            ->groupBy('attractions.category')
            ->select('attractions.category', DB::raw('count(*) as stop_count'))
            ->orderByDesc('stop_count')
            ->get()
            ->map(fn ($row) => [
                'category' => AttractionCategory::tryFrom((string) $row->category)?->getLabel() ?? (string) $row->category,
                'count' => (int) $row->stop_count,
            ])
            ->all();

        $scheduledIds = TripPlanItem::query()
            ->join('trip_plans', 'trip_plans.id', '=', 'trip_plan_items.trip_plan_id')
            ->where('trip_plans.created_at', '>=', $since)
            ->pluck('trip_plan_items.attraction_id')
            ->unique();

        $neverScheduled = Attraction::query()
            ->where('is_active', true)
            ->whereNotIn('id', $scheduledIds)
            ->orderBy('name')
            ->pluck('name')
            ->all();

        $withBooking = $this->plansWithBooking($plans);

        return [
            'days' => $days,
            'plans' => $plans->count(),
            'average_days' => $plans->isEmpty() ? 0.0 : round((float) $plans->avg('days'), 1),
            'rated_plans' => $rated->count(),
            'average_rating' => $rated->isEmpty() ? null : round((float) $rated->avg('rating'), 1),
            'fallback_plans' => $plans->where('generation_method', 'fallback')->count(),
            'average_generation_ms' => $plans->whereNotNull('generation_ms')->isEmpty() ? null : (int) round((float) $plans->whereNotNull('generation_ms')->avg('generation_ms')),
            'shared_plans' => $plans->whereNotNull('share_token')->count(),
            'plans_with_booking' => $withBooking,
            'booking_conversion_percent' => $plans->isEmpty() ? null : round($withBooking / $plans->count() * 100, 1),
            'top_attractions' => $topAttractions,
            'never_scheduled' => $neverScheduled,
            'categories' => $categories,
        ];
    }

    /**
     * Plans whose guest holds a non-cancelled booking overlapping the
     * planned dates.
     *
     * @param  Collection<int, TripPlan>  $plans
     */
    private function plansWithBooking($plans): int
    {
        if ($plans->isEmpty()) {
            return 0;
        }

        $bookingsByGuest = Booking::query()
            ->whereIn('guest_id', $plans->pluck('guest_id')->unique())
            ->where('status', '!=', BookingStatus::Cancelled)
            ->get(['guest_id', 'check_in_date', 'check_out_date'])
            ->groupBy('guest_id');

        return $plans->filter(function (TripPlan $plan) use ($bookingsByGuest) {
            $startsOn = ($plan->starts_on ?? $plan->created_at)->copy()->startOfDay();
            $endsOn = $startsOn->copy()->addDays($plan->days - 1);

            return ($bookingsByGuest[$plan->guest_id] ?? collect())->contains(
                fn (Booking $booking) => $booking->check_in_date <= $endsOn && $booking->check_out_date >= $startsOn,
            );
        })->count();
    }
}
