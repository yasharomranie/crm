<?php

namespace App\Domain\Hospitality;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomBlock;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The «تقویم اشغال» (tape chart): one row per active room, one column per
 * night of a window, with each booking laid across the nights it covers.
 * A cell is either a free night or a booking bar that spans several nights
 * (clipped at the window's edges, with flags saying so).
 */
class OccupancyCalendar
{
    public const MAX_DAYS = 62;

    /** Cancelled bookings hold no room, so they never appear. */
    private const SHOWN = [BookingStatus::Pending, BookingStatus::Confirmed, BookingStatus::CheckedIn, BookingStatus::CheckedOut];

    /**
     * @return array{days: list<array{date: CarbonInterface, occupied: int, blocked: int, sellable: int, is_today: bool}>, rows: list<array{room: Room, cells: list<array<string, mixed>>}>, rooms: int}
     */
    public function build(CarbonInterface $start, int $days = 14, ?int $roomTypeId = null): array
    {
        $start = $start->copy()->startOfDay();
        $days = max(1, min(self::MAX_DAYS, $days));
        $end = $start->copy()->addDays($days);

        $rooms = Room::query()
            ->with('roomType')
            ->where('is_active', true)
            ->when($roomTypeId, fn ($query) => $query->where('room_type_id', $roomTypeId))
            ->orderBy('room_type_id')
            ->orderBy('name')
            ->get();

        $bookings = Booking::query()
            ->with('guest')
            ->whereIn('room_id', $rooms->modelKeys())
            ->whereIn('status', self::SHOWN)
            ->whereDate('check_in_date', '<', $end->toDateString())
            ->whereDate('check_out_date', '>', $start->toDateString())
            ->get()
            ->groupBy('room_id');

        $blocks = RoomBlock::query()
            ->whereIn('room_id', $rooms->modelKeys())
            ->overlapping($start, $end)
            ->get()
            ->groupBy('room_id');

        $columns = [];

        for ($i = 0; $i < $days; $i++) {
            $columns[] = ['date' => $start->copy()->addDays($i), 'occupied' => 0, 'blocked' => 0, 'sellable' => $rooms->count(), 'is_today' => $start->copy()->addDays($i)->isToday()];
        }

        $rows = [];

        foreach ($rooms as $room) {
            $rows[] = ['room' => $room, 'cells' => $this->cellsFor($bookings->get($room->id, collect()), $blocks->get($room->id, collect()), $start, $days, $columns)];
        }

        return ['days' => $columns, 'rows' => $rows, 'rooms' => $rooms->count()];
    }

    /**
     * @param  Collection<int, Booking>  $bookings
     * @param  Collection<int, RoomBlock>  $blocks
     * @param  list<array{date: CarbonInterface, occupied: int, blocked: int, sellable: int, is_today: bool}>  $columns
     * @return list<array<string, mixed>>
     */
    private function cellsFor(Collection $bookings, Collection $blocks, CarbonInterface $start, int $days, array &$columns): array
    {
        $cells = [];
        $i = 0;

        while ($i < $days) {
            $date = $start->copy()->addDays($i);
            $booking = $bookings->first(fn (Booking $booking) => $booking->check_in_date->lte($date) && $booking->check_out_date->gt($date));

            if (! $booking) {
                $block = $blocks->first(fn (RoomBlock $block) => $block->starts_on->lte($date) && ($block->ends_on === null || $block->ends_on->gt($date)));

                if ($block) {
                    $columns[$i]['blocked']++;
                    $columns[$i]['sellable']--;
                    $cells[] = ['type' => 'blocked', 'date' => $date, 'reason' => $block->reason];
                } else {
                    $cells[] = ['type' => 'free', 'date' => $date];
                }

                $i++;

                continue;
            }

            $span = min($days - $i, (int) $date->diffInDays($booking->check_out_date));

            for ($k = 0; $k < $span; $k++) {
                $columns[$i + $k]['occupied']++;
            }

            $cells[] = [
                'type' => 'booking',
                'booking' => $booking,
                'span' => $span,
                'starts_before' => $booking->check_in_date->lt($start),
                'ends_after' => $booking->check_out_date->gt($start->copy()->addDays($days)),
            ];
            $i += $span;
        }

        return $cells;
    }
}
