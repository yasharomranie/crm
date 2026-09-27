<?php

namespace App\Domain\Webhooks;

use App\Enums\WebhookEventType;
use App\Jobs\DispatchWebhookJob;
use App\Models\WebhookSubscription;

/**
 * The single place that notifies an external system's subscribed
 * webhooks - called from GateValidationService (scan results),
 * BookingObserver (booking created) and DealObserver (stage changed), so
 * every event source shares the same lookup/queueing logic instead of
 * each caller reimplementing "find subscriptions, queue a job per one".
 */
class WebhookDispatcher
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(?int $externalApiClientId, WebhookEventType $event, array $payload): void
    {
        if (! $externalApiClientId) {
            return;
        }

        WebhookSubscription::query()
            ->where('external_api_client_id', $externalApiClientId)
            ->where('event_type', $event)
            ->where('is_active', true)
            ->each(fn (WebhookSubscription $subscription) => DispatchWebhookJob::dispatch($subscription->id, $event, $payload));
    }
}
