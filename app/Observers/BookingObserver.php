<?php

namespace App\Observers;

use App\Domain\Webhooks\WebhookDispatcher;
use App\Enums\BookingStatus;
use App\Enums\WebhookEventType;
use App\Jobs\NotifyWaitlistJob;
use App\Jobs\SendBookingMessageJob;
use App\Models\Booking;
use App\Models\BookingMessageSetting;
use App\Models\WaitlistEntry;

/**
 * Reacts to a booking's life: notifies the owning external client's
 * subscribed webhooks (created, confirmed, checked in/out, cancelled) and
 * queues the guest's confirmation SMS when that is switched on. Covers both
 * the Filament panel and the external API, since both just save a Booking.
 */
class BookingObserver
{
    public function __construct(private WebhookDispatcher $webhooks) {}

    public function created(Booking $booking): void
    {
        $this->webhooks->dispatch($booking->external_api_client_id, WebhookEventType::BookingCreated, $this->payload($booking));

        $this->queueConfirmation($booking);
    }

    public function updated(Booking $booking): void
    {
        if (! $booking->wasChanged('status')) {
            return;
        }

        $event = match ($booking->status) {
            BookingStatus::Confirmed => WebhookEventType::BookingConfirmed,
            BookingStatus::CheckedIn => WebhookEventType::BookingCheckedIn,
            BookingStatus::CheckedOut => WebhookEventType::BookingCheckedOut,
            BookingStatus::Cancelled => WebhookEventType::BookingCancelled,
            default => null,
        };

        if ($event) {
            $this->webhooks->dispatch($booking->external_api_client_id, $event, $this->payload($booking));
        }

        if ($booking->status === BookingStatus::Confirmed) {
            $this->queueConfirmation($booking);
        }

        if ($booking->status === BookingStatus::Cancelled && WaitlistEntry::query()->waiting()->exists()) {
            NotifyWaitlistJob::dispatch($booking->id);
        }
    }

    private function queueConfirmation(Booking $booking): void
    {
        if ($booking->status === BookingStatus::Confirmed && BookingMessageSetting::current()->confirmation_enabled) {
            SendBookingMessageJob::dispatch($booking->id, 'confirmation');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Booking $booking): array
    {
        return [
            'booking_id' => $booking->id,
            'reference_code' => $booking->reference_code,
            'status' => $booking->status->value,
            'check_in' => $booking->check_in_date->toDateString(),
            'check_out' => $booking->check_out_date->toDateString(),
        ] + ($booking->status === BookingStatus::Cancelled ? [
            'cancellation_kind' => $booking->cancellation_kind,
            'cancellation_fee' => (int) $booking->cancellation_fee,
        ] : []);
    }
}
