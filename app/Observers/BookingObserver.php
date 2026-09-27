<?php

namespace App\Observers;

use App\Domain\Webhooks\WebhookDispatcher;
use App\Enums\WebhookEventType;
use App\Models\Booking;

/**
 * Notifies the owning external client's subscribed webhooks the moment a
 * booking exists - covers both CreateBooking::handleRecordCreation() (the
 * Filament panel) and BookingController::store() (the external API),
 * since both just Booking::create() and this fires either way.
 */
class BookingObserver
{
    public function __construct(private WebhookDispatcher $webhooks) {}

    public function created(Booking $booking): void
    {
        $this->webhooks->dispatch($booking->external_api_client_id, WebhookEventType::BookingCreated, [
            'booking_id' => $booking->id,
            'reference_code' => $booking->reference_code,
            'status' => $booking->status->value,
            'check_in' => $booking->check_in_date->toDateString(),
            'check_out' => $booking->check_out_date->toDateString(),
        ]);
    }
}
