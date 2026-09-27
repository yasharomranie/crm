<?php

namespace App\Jobs;

use App\Enums\WebhookEventType;
use App\Models\WebhookSubscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

/**
 * POSTs one subscription's payload with an HMAC-SHA256 signature (over the
 * exact raw JSON body sent, via withBody - never Http::post()'s own $data
 * argument, so the signature the receiver recomputes always matches
 * byte-for-byte) so the receiver can verify the request really came from
 * this app and wasn't tampered with in transit.
 */
class DispatchWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60, 300, 900];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $webhookSubscriptionId,
        public WebhookEventType $eventType,
        public array $payload,
    ) {}

    public function handle(): void
    {
        $subscription = WebhookSubscription::find($this->webhookSubscriptionId);

        if (! $subscription || ! $subscription->is_active) {
            return;
        }

        $body = json_encode([
            'event' => $this->eventType->value,
            'data' => $this->payload,
            'sent_at' => now()->toIso8601String(),
        ]);

        $signature = hash_hmac('sha256', $body, $subscription->signing_secret);

        Http::withBody($body, 'application/json')
            ->withHeaders([
                'X-Webhook-Event' => $this->eventType->value,
                'X-Webhook-Signature' => $signature,
            ])
            ->timeout(10)
            ->post($subscription->target_url);

        $subscription->update(['last_triggered_at' => now()]);
    }
}
