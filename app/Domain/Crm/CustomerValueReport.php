<?php

namespace App\Domain\Crm;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Guest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lifetime value and churn picture of the guests who have actually stayed
 * (completed stays only, like the loyalty tier). Value is what a guest has
 * really paid across completed stays - a historical figure, not a forecast.
 *
 * Churn is judged against each guest's own rhythm:
 *  - lost:    no completed stay for a year or more;
 *  - at risk: two or more stays, and the current gap is more than 1.5x their
 *             usual gap between stays (and at least 60 days);
 *  - active:  everyone else.
 * A guest with one stay has no rhythm to compare with, so can only be
 * active or lost.
 */
class CustomerValueReport
{
    public const LOST_AFTER_DAYS = 365;

    public const AT_RISK_FACTOR = 1.5;

    public const AT_RISK_MIN_DAYS = 60;

    /**
     * @return Collection<int, array{guest_id: int, stays: int, spend: int, last_stay_at: Carbon, days_since_last: int, usual_gap_days: ?int, status: string}>
     */
    public function guestStats(): Collection
    {
        $stays = Booking::query()
            ->where('status', BookingStatus::CheckedOut)
            ->whereNotNull('actual_check_out_at')
            ->groupBy('guest_id')
            ->selectRaw('guest_id, count(*) as stays, min(actual_check_out_at) as first_at, max(actual_check_out_at) as last_at')
            ->get();

        $spend = DB::table('booking_charges')
            ->join('bookings', 'bookings.id', '=', 'booking_charges.booking_id')
            ->where('bookings.status', BookingStatus::CheckedOut->value)
            ->groupBy('bookings.guest_id')
            ->selectRaw('bookings.guest_id as guest_id, sum(booking_charges.quantity * booking_charges.unit_amount) as spend')
            ->pluck('spend', 'guest_id');

        return $stays->map(function ($row) use ($spend) {
            $first = Carbon::parse($row->first_at);
            $last = Carbon::parse($row->last_at);
            $count = (int) $row->stays;
            $usualGap = $count > 1 ? (int) round($first->diffInDays($last) / ($count - 1)) : null;
            $sinceLast = (int) $last->diffInDays(now());

            return [
                'guest_id' => (int) $row->guest_id,
                'stays' => $count,
                'spend' => (int) ($spend[$row->guest_id] ?? 0),
                'last_stay_at' => $last,
                'days_since_last' => $sinceLast,
                'usual_gap_days' => $usualGap,
                'status' => $this->statusFor($sinceLast, $usualGap),
            ];
        })->values();
    }

    public function statusFor(int $daysSinceLast, ?int $usualGapDays): string
    {
        if ($daysSinceLast >= self::LOST_AFTER_DAYS) {
            return 'lost';
        }

        if ($usualGapDays !== null && $daysSinceLast > max(self::AT_RISK_MIN_DAYS, $usualGapDays * self::AT_RISK_FACTOR)) {
            return 'at_risk';
        }

        return 'active';
    }

    /**
     * @return array{
     *     customers: int, revenue: int, average_value: int, repeat_rate_percent: ?float,
     *     active: int, at_risk: int, lost: int,
     *     tiers: list<array{tier: string, guests: int, revenue: int}>,
     *     top_guests: list<array{guest: Guest, stays: int, spend: int}>,
     *     at_risk_guests: list<array{guest: Guest, stays: int, spend: int, days_since_last: int, usual_gap_days: ?int}>
     * }
     */
    public function build(int $listSize = 20): array
    {
        $stats = $this->guestStats();
        $guests = Guest::query()->whereIn('id', $stats->pluck('guest_id'))->get()->keyBy('id');
        $stats = $stats->filter(fn (array $row) => $guests->has($row['guest_id']))->values();

        $revenue = (int) $stats->sum('spend');
        $customers = $stats->count();

        $tiers = $stats
            ->groupBy(fn (array $row) => Guest::tierFor($row['stays'], $row['spend'])->getLabel())
            ->map(fn (Collection $rows, string $tier) => ['tier' => $tier, 'guests' => $rows->count(), 'revenue' => (int) $rows->sum('spend')])
            ->sortByDesc('revenue')
            ->values()
            ->all();

        return [
            'customers' => $customers,
            'revenue' => $revenue,
            'average_value' => $customers > 0 ? intdiv($revenue, $customers) : 0,
            'repeat_rate_percent' => $customers > 0 ? round($stats->where('stays', '>=', 2)->count() / $customers * 100, 1) : null,
            'active' => $stats->where('status', 'active')->count(),
            'at_risk' => $stats->where('status', 'at_risk')->count(),
            'lost' => $stats->where('status', 'lost')->count(),
            'tiers' => $tiers,
            'top_guests' => $stats->sortByDesc('spend')->take($listSize)
                ->map(fn (array $row) => ['guest' => $guests[$row['guest_id']], 'stays' => $row['stays'], 'spend' => $row['spend']])
                ->values()->all(),
            'at_risk_guests' => $stats->where('status', 'at_risk')->sortByDesc('spend')->take($listSize * 2)
                ->map(fn (array $row) => [
                    'guest' => $guests[$row['guest_id']],
                    'stays' => $row['stays'],
                    'spend' => $row['spend'],
                    'days_since_last' => $row['days_since_last'],
                    'usual_gap_days' => $row['usual_gap_days'],
                ])
                ->values()->all(),
        ];
    }
}
