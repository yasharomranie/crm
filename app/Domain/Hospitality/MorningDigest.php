<?php

namespace App\Domain\Hospitality;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\HandoverNote;
use App\Models\RoomBlock;
use App\Models\User;
use App\Notifications\HotelMorningDigestNotification;
use Carbon\CarbonInterface;
use Spatie\Permission\Models\Permission;

/**
 * Once a morning, tells the people who run the front desk what today looks
 * like: arrivals, departures, guests in the house, rooms out of service,
 * open handover notes and the money taken yesterday. A day with nothing in
 * the house and nothing arriving sends nothing.
 */
class MorningDigest
{
    private const PERMISSION = 'today-arrivals.access';

    /**
     * @return array{arrivals: int, departures: int, in_house: int, rooms_out: int, open_notes: int, yesterday_cash: int}
     */
    public function summary(CarbonInterface $today): array
    {
        $today = $today->copy()->startOfDay();

        return [
            'arrivals' => Booking::query()
                ->whereIn('status', [BookingStatus::Pending, BookingStatus::Confirmed])
                ->whereDate('check_in_date', $today)
                ->count(),
            'departures' => Booking::query()
                ->where('status', BookingStatus::CheckedIn)
                ->whereDate('check_out_date', '<=', $today)
                ->count(),
            'in_house' => Booking::query()->where('status', BookingStatus::CheckedIn)->count(),
            'rooms_out' => RoomBlock::query()->overlapping($today, $today->copy()->addDay())->count(),
            'open_notes' => HandoverNote::query()->open()->count(),
            'yesterday_cash' => (int) BookingPayment::query()
                ->whereDate('paid_at', $today->copy()->subDay())
                ->get()
                ->sum(fn (BookingPayment $payment) => $payment->signedAmount()),
        ];
    }

    /**
     * @return int how many people were notified
     */
    public function notifyStaff(?CarbonInterface $today = null): int
    {
        $summary = $this->summary($today ?? today());

        if ($summary['arrivals'] + $summary['departures'] + $summary['in_house'] === 0) {
            return 0;
        }

        $recipients = User::query()->role('admin')->get();

        if (Permission::where('name', self::PERMISSION)->exists()) {
            $recipients = $recipients->merge(User::query()->permission(self::PERMISSION)->get())->unique('id');
        }

        foreach ($recipients as $recipient) {
            $recipient->notify(new HotelMorningDigestNotification($summary));
        }

        return $recipients->count();
    }
}
