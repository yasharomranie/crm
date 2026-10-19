<?php

namespace App\Domain\Hospitality;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Room;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The numbers a hotel is run by, for a period of nights:
 *
 *  - occupancy: room-nights sold / room-nights available;
 *  - ADR: room revenue per room-night sold;
 *  - RevPAR: room revenue per room-night available;
 *  - the same figures split by sales channel and by booking source;
 *  - cancellations, no-shows and the average length of stay;
 *  - a 30-day forecast of the nights already booked.
 *
 * A booking counts as sold when it is confirmed, checked in or checked out
 * (a pending booking is only a hope, a cancelled one sold nothing). A booking's
 * folio is spread evenly over its nights, so a stay across the end of the
 * period is counted only for the nights inside it. Every active room counts as
 * available on every night.
 */
class HotelReport
{
    public const FORECAST_DAYS = 30;

    private const SOLD = [BookingStatus::Confirmed, BookingStatus::CheckedIn, BookingStatus::CheckedOut];

    /**
     * @return array<string, mixed>
     */
    public function build(CarbonInterface $from, CarbonInterface $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();
        $nightsInPeriod = (int) $from->diffInDays($to) + 1;
        $rooms = Room::query()->where('is_active', true)->count();
        $available = $rooms * $nightsInPeriod;

        $sold = $this->bookingsOverlapping($from, $to, self::SOLD);

        $rows = $sold->map(fn (Booking $booking) => $this->row($booking, $from, $to));
        $roomNights = (int) $rows->sum('room_nights');
        $revenue = (int) round($rows->sum('revenue'));

        $cancelled = Booking::query()
            ->where('status', BookingStatus::Cancelled)
            ->whereDate('check_in_date', '>=', $from->toDateString())
            ->whereDate('check_in_date', '<=', $to->toDateString())
            ->get();
        $allArrivals = $cancelled->count() + $sold->filter(fn (Booking $booking) => $booking->check_in_date->betweenIncluded($from, $to))->count();

        return [
            'from' => $from,
            'to' => $to,
            'rooms' => $rooms,
            'room_nights_available' => $available,
            'room_nights_sold' => $roomNights,
            'occupancy_percent' => $available > 0 ? round($roomNights / $available * 100, 1) : null,
            'revenue' => $revenue,
            'adr' => $roomNights > 0 ? (int) round($revenue / $roomNights) : null,
            'revpar' => $available > 0 ? (int) round($revenue / $available) : null,
            'bookings' => $sold->count(),
            'average_stay_nights' => $sold->isNotEmpty() ? round($sold->avg(fn (Booking $booking) => $booking->nights()), 1) : null,
            'cancellations' => $cancelled->count(),
            'no_shows' => $cancelled->where('cancellation_kind', Booking::CANCELLED_NO_SHOW)->count(),
            'cancellation_rate_percent' => $allArrivals > 0 ? round($cancelled->count() / $allArrivals * 100, 1) : null,
            'cancellation_fees' => (int) $cancelled->sum('cancellation_fee'),
            'by_channel' => $this->breakdown($sold, $rows, fn (Booking $booking) => $booking->channel?->name ?? 'فروش مستقیم'),
            'by_source' => $this->breakdown($sold, $rows, fn (Booking $booking) => $booking->source->getLabel()),
            'forecast' => $this->forecast($rooms),
        ];
    }

    /**
     * Nights already booked for the next FORECAST_DAYS days: sold (firm) and
     * pending (not yet confirmed), against the active rooms.
     *
     * @return list<array{date: string, sold: int, pending: int, available: int, occupancy_percent: ?float}>
     */
    public function forecast(int $rooms): array
    {
        $start = today();
        $end = today()->addDays(self::FORECAST_DAYS - 1);

        $firm = $this->bookingsOverlapping($start, $end, self::SOLD);
        $pending = $this->bookingsOverlapping($start, $end, [BookingStatus::Pending]);

        $days = [];

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $sold = $firm->filter(fn (Booking $booking) => $this->staysOn($booking, $day))->count();

            $days[] = [
                'date' => $day->toDateString(),
                'sold' => $sold,
                'pending' => $pending->filter(fn (Booking $booking) => $this->staysOn($booking, $day))->count(),
                'available' => $rooms,
                'occupancy_percent' => $rooms > 0 ? round($sold / $rooms * 100, 1) : null,
            ];
        }

        return $days;
    }

    /**
     * @param  list<BookingStatus>  $statuses
     * @return Collection<int, Booking>
     */
    private function bookingsOverlapping(CarbonInterface $from, CarbonInterface $to, array $statuses): Collection
    {
        return Booking::query()
            ->with(['charges', 'channel'])
            ->whereIn('status', $statuses)
            ->whereDate('check_in_date', '<=', $to->toDateString())
            ->whereDate('check_out_date', '>', $from->toDateString())
            ->get();
    }

    private function staysOn(Booking $booking, CarbonInterface $night): bool
    {
        return $booking->check_in_date->lte($night) && $booking->check_out_date->gt($night);
    }

    /**
     * @return array{room_nights: int, revenue: float}
     */
    private function row(Booking $booking, CarbonInterface $from, CarbonInterface $to): array
    {
        $start = $booking->check_in_date->max($from);
        $end = $booking->check_out_date->min($to->copy()->addDay());
        $inside = max(0, (int) $start->diffInDays($end));

        return [
            'room_nights' => $inside,
            'revenue' => $booking->totalAmount() * $inside / $booking->nights(),
        ];
    }

    /**
     * @param  Collection<int, Booking>  $bookings
     * @param  Collection<int, array{room_nights: int, revenue: float}>  $rows
     * @return list<array{name: string, bookings: int, room_nights: int, revenue: int, share_percent: ?float, adr: ?int}>
     */
    private function breakdown(Collection $bookings, Collection $rows, callable $label): array
    {
        $total = $rows->sum('revenue');

        return $bookings->keys()
            ->groupBy(fn (int $index) => $label($bookings[$index]))
            ->map(function (Collection $indexes, string $name) use ($rows) {
                $roomNights = (int) $indexes->sum(fn (int $index) => $rows[$index]['room_nights']);
                $revenue = (float) $indexes->sum(fn (int $index) => $rows[$index]['revenue']);

                return ['name' => $name, 'bookings' => $indexes->count(), 'room_nights' => $roomNights, 'revenue' => (int) round($revenue), 'adr' => $roomNights > 0 ? (int) round($revenue / $roomNights) : null];
            })
            ->map(fn (array $row) => $row + ['share_percent' => $total > 0 ? round($row['revenue'] / $total * 100, 1) : null])
            ->sortByDesc('revenue')
            ->values()
            ->all();
    }
}
