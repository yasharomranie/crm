<?php

namespace App\Observers;

use App\Domain\Webhooks\WebhookDispatcher;
use App\Enums\WebhookEventType;
use App\Models\BookingPayment;

/**
 * Tells the owning external client's webhooks that money was recorded on a
 * booking (a payment, an advance or a refund).
 */
class BookingPaymentObserver
{
    public function __construct(private WebhookDispatcher $webhooks) {}

    public function created(BookingPayment $payment): void
    {
        $booking = $payment->booking;

        $this->webhooks->dispatch($booking->external_api_client_id, WebhookEventType::BookingPaymentRecorded, [
            'booking_id' => $booking->id,
            'reference_code' => $booking->reference_code,
            'kind' => $payment->kind->value,
            'method' => $payment->method->value,
            'amount' => (int) $payment->amount,
            'paid_amount' => $booking->paidAmount(),
            'balance_due' => $booking->balanceDue(),
            'payment_status' => $booking->paymentStatus()->value,
        ]);
    }
}
