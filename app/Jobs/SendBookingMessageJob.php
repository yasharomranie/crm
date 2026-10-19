<?php

namespace App\Jobs;

use App\Domain\Crm\BookingMessenger;
use App\Models\Booking;
use App\Models\GuestFeedback;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends one automatic booking SMS off the request, so a slow SMS gateway
 * never delays saving a booking or checking a guest out.
 */
class SendBookingMessageJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  'confirmation'|'feedback'  $type
     */
    public function __construct(public int $bookingId, public string $type, public ?int $feedbackId = null) {}

    public function handle(BookingMessenger $messenger): void
    {
        $booking = Booking::query()->with(['guest', 'room'])->find($this->bookingId);

        if (! $booking) {
            return;
        }

        if ($this->type === 'confirmation') {
            $messenger->sendConfirmation($booking);

            return;
        }

        $feedback = GuestFeedback::query()->find($this->feedbackId);

        if ($feedback) {
            $messenger->sendFeedbackRequest($booking, $feedback);
        }
    }
}
