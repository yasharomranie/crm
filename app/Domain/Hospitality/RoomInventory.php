<?php

namespace App\Domain\Hospitality;

use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomBlock;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The one place that answers «how many units do we have, and in what state?»
 * The rack, the dashboard, the reports, the night audit and the AI assistant
 * all read it, so they can never disagree again:
 *
 *  - a unit with a guest checked in is occupied, whatever its stored status says;
 *  - a unit with an out-of-service block tonight is out of service (RoomBlock
 *    is the single source of truth - the room's own status feeds it, see Room::booted());
 *  - only units that are neither of those can be sold, and only they count as
 *    «available» room-nights for occupancy and RevPAR.
 */
class RoomInventory
{
    /**
     * The state a unit is really in right now.
     */
    public static function effectiveStatus(Room $room, ?Booking $stay, ?RoomBlock $block): RoomStatus
    {
        return match (true) {
            $stay !== null => RoomStatus::Occupied,
            $block !== null => RoomStatus::OutOfService,
            // «اشغال» with nobody in the unit is a stale flag: it can be sold again.
            $room->status === RoomStatus::Occupied, $room->status === RoomStatus::OutOfService => RoomStatus::Available,
            default => $room->status,
        };
    }

    /**
     * Today's picture of the whole property.
     *
     * @return array{total: int, sellable: int, occupied: int, available: int, needs_cleaning: int, out_of_service: int, occupancy_percent: ?float}
     */
    public function summary(?CarbonInterface $date = null): array
    {
        $date = ($date ?? today())->copy()->startOfDay();

        $rooms = Room::query()->where('is_active', true)->get(['id', 'status']);
        $occupiedIds = Booking::query()->where('status', BookingStatus::CheckedIn)->pluck('room_id')->unique();
        $blockedIds = RoomBlock::query()->overlapping($date, $date->copy()->addDay())->pluck('room_id')->unique();

        $occupied = $rooms->whereIn('id', $occupiedIds)->count();
        $outOfService = $rooms->whereNotIn('id', $occupiedIds)->whereIn('id', $blockedIds)->count();
        $rest = $rooms->whereNotIn('id', $occupiedIds)->whereNotIn('id', $blockedIds);
        $needsCleaning = $rest->where('status', RoomStatus::NeedsCleaning)->count();
        $available = $rest->count() - $needsCleaning;
        $sellable = $rooms->count() - $rooms->whereIn('id', $blockedIds)->count();

        return [
            'total' => $rooms->count(),
            'sellable' => $sellable,
            'occupied' => $occupied,
            'available' => $available,
            'needs_cleaning' => $needsCleaning,
            'out_of_service' => $outOfService,
            'occupancy_percent' => $sellable > 0 ? round($occupied / $sellable * 100, 1) : null,
        ];
    }

    /**
     * Units that can be sold on a night (active and not blocked).
     */
    public function sellableOn(CarbonInterface $date): int
    {
        $date = $date->copy()->startOfDay();

        return Room::query()
            ->where('is_active', true)
            ->whereDoesntHave('blocks', fn ($blocks) => $blocks->overlapping($date, $date->copy()->addDay()))
            ->count();
    }

    /**
     * Sellable room-nights from $from to $to, both days included.
     */
    public function sellableRoomNights(CarbonInterface $from, CarbonInterface $to): int
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();
        $total = 0;

        foreach ($this->sellablePerNight($from, $to) as $count) {
            $total += $count;
        }

        return $total;
    }

    /**
     * Sellable units on each night from $from to $to (inclusive), keyed by Y-m-d.
     *
     * @return array<string, int>
     */
    public function sellablePerNight(CarbonInterface $from, CarbonInterface $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();

        $active = Room::query()->where('is_active', true)->pluck('id');
        $blocks = RoomBlock::query()
            ->whereIn('room_id', $active)
            ->overlapping($from, $to->copy()->addDay())
            ->get(['room_id', 'starts_on', 'ends_on']);

        $perNight = [];

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $blocked = $blocks
                ->filter(fn (RoomBlock $block) => $block->starts_on->lte($day) && ($block->ends_on === null || $block->ends_on->gt($day)))
                ->pluck('room_id')
                ->unique()
                ->count();

            $perNight[$day->toDateString()] = $active->count() - $blocked;
        }

        return $perNight;
    }

    /**
     * Ids of units blocked on at least one night of [$from, $to).
     *
     * @return Collection<int, int>
     */
    public function blockedRoomIds(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return RoomBlock::query()->overlapping($from, $to)->pluck('room_id')->unique()->values();
    }
}
