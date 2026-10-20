<?php

namespace App\Domain\Hospitality;

use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\DayClosing;
use App\Models\Room;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * «بستن روز»: what the front desk checks before the day is over.
 *
 *  - issues: stays that need a decision (guests who never arrived, guests who
 *    should have left, unpaid balances, unsettled deposits, rooms whose status
 *    disagrees with the bookings);
 *  - cash: the money taken that day, by method, refunds taken off;
 *  - occupancy: how many rooms were in the house.
 *
 * Closing the day stores a frozen copy of these figures; open issues do not
 * block it (a real desk sometimes has to close with something pending) but
 * their number is recorded.
 */
class NightAudit
{
    /** How far back a finished stay's unpaid balance is still chased. */
    private const UNPAID_LOOKBACK_DAYS = 30;

    /**
     * @return array<string, mixed>
     */
    public function build(CarbonInterface $date): array
    {
        $date = $date->copy()->startOfDay();

        $issues = [
            'never_arrived' => $this->neverArrived($date),
            'overdue_departures' => $this->overdueDepartures($date),
            'departing_today' => $this->departingToday($date),
            'unpaid' => $this->unpaid($date),
            'unsettled_deposits' => $this->unsettledDeposits($date),
            'room_mismatches' => $this->roomMismatches(),
        ];

        $rooms = Room::query()->where('is_active', true)->count();
        $inHouse = Booking::query()->where('status', BookingStatus::CheckedIn)->count();

        return [
            'date' => $date,
            'issues' => $issues,
            'issue_count' => collect($issues)->sum(fn (Collection $rows) => $rows->count()),
            'cash' => $this->cash($date),
            'rooms' => $rooms,
            'in_house' => $inHouse,
            'occupancy_percent' => $rooms > 0 ? round($inHouse / $rooms * 100, 1) : null,
            'closing' => DayClosing::query()->whereDate('date', $date->toDateString())->first(),
        ];
    }

    /**
     * Freezes the day's figures. A day can be closed once.
     *
     * @throws InvalidArgumentException
     */
    public function close(CarbonInterface $date, ?int $userId = null): DayClosing
    {
        $date = $date->copy()->startOfDay();

        if ($date->isFuture()) {
            throw new InvalidArgumentException('روز آینده را نمی‌توان بست.');
        }

        if (DayClosing::query()->whereDate('date', $date->toDateString())->exists()) {
            throw new InvalidArgumentException('این روز قبلاً بسته شده است.');
        }

        $report = $this->build($date);

        return DayClosing::query()->create([
            'date' => $date->toDateString(),
            'closed_by' => $userId,
            'closed_at' => now(),
            'snapshot' => [
                'cash' => $report['cash'],
                'rooms' => $report['rooms'],
                'in_house' => $report['in_house'],
                'occupancy_percent' => $report['occupancy_percent'],
                'issue_counts' => collect($report['issues'])->map(fn (Collection $rows) => $rows->count())->all(),
            ],
        ]);
    }

    /**
     * Pending or confirmed bookings whose arrival day has come and gone with no check-in.
     *
     * @return Collection<int, Booking>
     */
    private function neverArrived(CarbonInterface $date): Collection
    {
        return $this->bookings()
            ->whereIn('status', [BookingStatus::Pending, BookingStatus::Confirmed])
            ->whereDate('check_in_date', '<=', $date->toDateString())
            ->orderBy('check_in_date')
            ->get();
    }

    /**
     * Guests still in the house after their check-out day.
     *
     * @return Collection<int, Booking>
     */
    private function overdueDepartures(CarbonInterface $date): Collection
    {
        return $this->bookings()
            ->where('status', BookingStatus::CheckedIn)
            ->whereDate('check_out_date', '<', $date->toDateString())
            ->orderBy('check_out_date')
            ->get();
    }

    /**
     * Guests who should leave on this day and have not been checked out yet.
     *
     * @return Collection<int, Booking>
     */
    private function departingToday(CarbonInterface $date): Collection
    {
        return $this->bookings()
            ->where('status', BookingStatus::CheckedIn)
            ->whereDate('check_out_date', '=', $date->toDateString())
            ->get();
    }

    /**
     * In-house and recently finished stays that still owe money.
     *
     * @return Collection<int, Booking>
     */
    private function unpaid(CarbonInterface $date): Collection
    {
        return $this->bookings()
            ->where(fn ($query) => $query
                ->where('status', BookingStatus::CheckedIn)
                ->orWhere(fn ($finished) => $finished
                    ->where('status', BookingStatus::CheckedOut)
                    ->whereDate('check_out_date', '>=', $date->copy()->subDays(self::UNPAID_LOOKBACK_DAYS)->toDateString())))
            ->get()
            ->filter(fn (Booking $booking) => $booking->balanceDue() > 0)
            ->values();
    }

    /**
     * Damage deposits still held for a stay that is over (or due to be).
     *
     * @return Collection<int, Booking>
     */
    private function unsettledDeposits(CarbonInterface $date): Collection
    {
        return $this->bookings()
            ->whereHas('damageDeposit', fn ($query) => $query->where('held_amount', '>', 0)->whereNull('settled_at'))
            ->where(fn ($query) => $query
                ->where('status', BookingStatus::CheckedOut)
                ->orWhere(fn ($staying) => $staying->where('status', BookingStatus::CheckedIn)->whereDate('check_out_date', '<=', $date->toDateString())))
            ->get();
    }

    /**
     * Rooms whose status does not match the bookings: «اشغال» with nobody
     * checked in, or a checked-in guest in a room not marked occupied.
     *
     * @return Collection<int, array{room: Room, problem: string}>
     */
    private function roomMismatches(): Collection
    {
        $occupiedRoomIds = Booking::query()->where('status', BookingStatus::CheckedIn)->pluck('room_id')->unique();

        $emptyButOccupied = Room::query()->where('is_active', true)
            ->where('status', RoomStatus::Occupied)
            ->whereNotIn('id', $occupiedRoomIds)
            ->get()
            ->map(fn (Room $room) => ['room' => $room, 'problem' => 'وضعیت «اشغال» است ولی مهمان چک‌این‌شده‌ای ندارد']);

        $occupiedButMarkedFree = Room::query()->whereIn('id', $occupiedRoomIds)
            ->where('status', '!=', RoomStatus::Occupied)
            ->get()
            ->map(fn (Room $room) => ['room' => $room, 'problem' => 'مهمان چک‌این‌شده دارد ولی وضعیت «اشغال» نیست']);

        return $emptyButOccupied->concat($occupiedButMarkedFree)->values();
    }

    /**
     * Money taken that day by payment method (refunds taken off), and the total.
     *
     * @return array{by_method: array<string, int>, total: int}
     */
    private function cash(CarbonInterface $date): array
    {
        $byMethod = BookingPayment::query()
            ->whereDate('paid_at', $date->toDateString())
            ->get()
            ->groupBy(fn (BookingPayment $payment) => $payment->method->value)
            ->map(fn (Collection $payments) => (int) $payments->sum(fn (BookingPayment $payment) => $payment->signedAmount()))
            ->all();

        return ['by_method' => $byMethod, 'total' => (int) array_sum($byMethod)];
    }

    /**
     * @return Builder<Booking>
     */
    private function bookings()
    {
        return Booking::query()->with(['guest', 'room', 'charges', 'payments', 'damageDeposit']);
    }
}
