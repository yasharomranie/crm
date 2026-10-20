<?php

namespace App\Jobs;

use App\Domain\Hospitality\WaitlistNotifier;
use App\Models\Booking;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Tells the waiting list about a room a cancellation just freed, off the
 * request so a slow SMS gateway never delays cancelling.
 */
class NotifyWaitlistJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $bookingId) {}

    public function handle(WaitlistNotifier $notifier): void
    {
        $booking = Booking::query()->with('room')->find($this->bookingId);

        if ($booking) {
            $notifier->afterBookingCancelled($booking);
        }
    }
}
