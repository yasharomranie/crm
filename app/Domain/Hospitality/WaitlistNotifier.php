<?php

namespace App\Domain\Hospitality;

use App\Domain\Crm\GuestMessenger;
use App\Enums\MessageDeliveryStatus;
use App\Models\Booking;
use App\Models\WaitlistEntry;

/**
 * Texts the guests on the waiting list when a cancellation (or a no-show, or a
 * released hold) frees a room they wanted. Each entry is told once; the guest
 * is not promised the room, only that one is free now.
 */
class WaitlistNotifier
{
    public function __construct(private GuestMessenger $messenger) {}

    /**
     * @return int number of guests texted
     */
    public function afterBookingCancelled(Booking $cancelled): int
    {
        $notified = 0;

        WaitlistEntry::query()
            ->waiting()
            ->with('guest')
            ->whereDate('check_in_date', '<', $cancelled->check_out_date->toDateString())
            ->whereDate('check_out_date', '>', $cancelled->check_in_date->toDateString())
            ->where(fn ($query) => $query->whereNull('room_type_id')->orWhere('room_type_id', $cancelled->room->room_type_id))
            ->orderBy('id')
            ->get()
            ->each(function (WaitlistEntry $entry) use (&$notified) {
                if ($entry->hasFreeRoom() && $this->notify($entry)) {
                    $notified++;
                }
            });

        return $notified;
    }

    /**
     * Texts one entry now (also used by the «اعلام» button).
     */
    public function notify(WaitlistEntry $entry): bool
    {
        $message = $this->messenger->sendSms($entry->guest, sprintf(
            '%s عزیز، برای %s تا %s اتاق خالی شد. برای قطعی‌کردن هرچه زودتر با ما تماس بگیرید.',
            $entry->guest->name,
            $entry->check_in_date->toJalali('Y/m/d'),
            $entry->check_out_date->toJalali('Y/m/d'),
        ));

        if ($message->status !== MessageDeliveryStatus::Sent) {
            return false;
        }

        $entry->update(['status' => WaitlistEntry::STATUS_NOTIFIED, 'notified_at' => now()]);

        return true;
    }
}
