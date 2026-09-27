<?php

namespace Tests\Feature\Webhooks;

use App\Enums\WebhookEventType;
use App\Jobs\DispatchWebhookJob;
use App\Models\WebhookSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DispatchWebhookJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_job_posts_a_correctly_signed_payload_and_records_last_triggered_at(): void
    {
        Http::fake(['example.com/*' => Http::response('ok', 200)]);
        $subscription = WebhookSubscription::factory()->create([
            'target_url' => 'https://example.com/hooks/ticket-scanned',
            'signing_secret' => 'shhh-secret',
        ]);

        (new DispatchWebhookJob($subscription->id, WebhookEventType::TicketScanned, ['ticket_number' => 'TKT-1']))->handle();

        Http::assertSent(function (Request $request) use ($subscription) {
            $body = $request->body();
            $expectedSignature = hash_hmac('sha256', $body, 'shhh-secret');

            return $request->url() === $subscription->target_url
                && $request->hasHeader('X-Webhook-Signature', $expectedSignature)
                && $request->hasHeader('X-Webhook-Event', 'ticket.scanned')
                && json_decode($body, true)['data']['ticket_number'] === 'TKT-1';
        });

        $this->assertNotNull($subscription->fresh()->last_triggered_at);
    }

    public function test_the_job_does_nothing_for_an_inactive_subscription(): void
    {
        Http::fake();
        $subscription = WebhookSubscription::factory()->create(['is_active' => false]);

        (new DispatchWebhookJob($subscription->id, WebhookEventType::TicketScanned, []))->handle();

        Http::assertNothingSent();
    }
}
