<?php

namespace Tests\Feature\Webhooks;

use App\Domain\Ticketing\TicketIssuer;
use App\Enums\DealStage;
use App\Enums\TicketSourceType;
use App\Enums\WebhookEventType;
use App\Jobs\DispatchWebhookJob;
use App\Models\Deal;
use App\Models\ExternalApiClient;
use App\Models\Gate;
use App\Models\GateRule;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\TicketVariant;
use App\Models\TicketVariantPrice;
use App\Models\WebhookSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Verifies the three domain hooks (GateValidationService::log(),
 * BookingObserver, DealObserver) correctly resolve "which client owns
 * this" and only queue a job when that client has a matching active
 * subscription - the job's own HTTP/signature behavior is covered
 * separately in DispatchWebhookJobTest.
 */
class WebhookDispatchTest extends TestCase
{
    use RefreshDatabase;

    private function headers(ExternalApiClient $client, string $secret = 'test-secret'): array
    {
        return ['X-API-Key' => $client->api_key, 'X-API-Secret' => $secret];
    }

    public function test_a_successful_gate_scan_queues_the_subscribed_webhook(): void
    {
        Queue::fake();
        $client = ExternalApiClient::factory()->create();
        WebhookSubscription::factory()->create([
            'external_api_client_id' => $client->id,
            'event_type' => WebhookEventType::TicketScanned,
        ]);
        $variant = TicketVariant::factory()->create();
        TicketVariantPrice::factory()->create(['ticket_variant_id' => $variant->id, 'amount' => 100000]);
        $gate = Gate::factory()->create();
        GateRule::factory()->create(['gate_id' => $gate->id, 'ticket_variant_id' => $variant->id]);
        $order = app(TicketIssuer::class)->issue([
            'source_type' => TicketSourceType::ExternalPos,
            'items' => [['ticket_variant_id' => $variant->id, 'quantity' => 1]],
            'external_api_client_id' => $client->id,
        ]);
        $ticket = $order->items->first()->tickets->first();

        $this->postJson("/api/v1/external/gates/{$gate->code}/scan", [
            'qr_token' => $ticket->qrToken->token,
        ], $this->headers($client))->assertOk();

        Queue::assertPushed(DispatchWebhookJob::class, fn (DispatchWebhookJob $job) => $job->eventType === WebhookEventType::TicketScanned);
    }

    public function test_a_rejected_gate_scan_does_not_queue_a_ticket_scanned_webhook(): void
    {
        Queue::fake();
        $client = ExternalApiClient::factory()->create();
        WebhookSubscription::factory()->create([
            'external_api_client_id' => $client->id,
            'event_type' => WebhookEventType::TicketScanned,
        ]);
        $gate = Gate::factory()->create();

        $this->postJson("/api/v1/external/gates/{$gate->code}/scan", [
            'qr_token' => 'does-not-exist',
        ], $this->headers($client))->assertOk();

        Queue::assertNotPushed(DispatchWebhookJob::class);
    }

    public function test_no_job_is_queued_when_the_client_has_no_matching_subscription(): void
    {
        Queue::fake();
        $client = ExternalApiClient::factory()->create();
        WebhookSubscription::factory()->create([
            'external_api_client_id' => $client->id,
            'event_type' => WebhookEventType::BookingCreated,
        ]);
        $variant = TicketVariant::factory()->create();
        TicketVariantPrice::factory()->create(['ticket_variant_id' => $variant->id, 'amount' => 100000]);
        $gate = Gate::factory()->create();
        GateRule::factory()->create(['gate_id' => $gate->id, 'ticket_variant_id' => $variant->id]);
        $order = app(TicketIssuer::class)->issue([
            'source_type' => TicketSourceType::ExternalPos,
            'items' => [['ticket_variant_id' => $variant->id, 'quantity' => 1]],
            'external_api_client_id' => $client->id,
        ]);
        $ticket = $order->items->first()->tickets->first();

        $this->postJson("/api/v1/external/gates/{$gate->code}/scan", [
            'qr_token' => $ticket->qrToken->token,
        ], $this->headers($client))->assertOk();

        Queue::assertNotPushed(DispatchWebhookJob::class);
    }

    public function test_creating_a_booking_through_the_api_queues_the_subscribed_webhook(): void
    {
        Queue::fake();
        $client = ExternalApiClient::factory()->create();
        WebhookSubscription::factory()->create([
            'external_api_client_id' => $client->id,
            'event_type' => WebhookEventType::BookingCreated,
        ]);
        $roomType = RoomType::factory()->create();
        $room = Room::factory()->create(['room_type_id' => $roomType->id]);

        $this->postJson('/api/v1/external/bookings', [
            'room_id' => $room->id,
            'check_in_date' => now()->addDay()->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(),
            'guest' => ['name' => 'مریم احمدی', 'mobile' => '09121112233'],
        ], [...$this->headers($client), 'Idempotency-Key' => 'wh-booking-1']);

        Queue::assertPushed(DispatchWebhookJob::class, fn (DispatchWebhookJob $job) => $job->eventType === WebhookEventType::BookingCreated);
    }

    public function test_changing_a_deals_stage_queues_the_subscribed_webhook(): void
    {
        Queue::fake();
        $client = ExternalApiClient::factory()->create();
        WebhookSubscription::factory()->create([
            'external_api_client_id' => $client->id,
            'event_type' => WebhookEventType::DealStageChanged,
        ]);
        $deal = Deal::create([
            'title' => 'فروش بسته VIP',
            'stage' => DealStage::NewLead,
            'external_api_client_id' => $client->id,
        ]);

        $deal->update(['stage' => DealStage::Negotiation]);

        Queue::assertPushed(DispatchWebhookJob::class, fn (DispatchWebhookJob $job) => $job->eventType === WebhookEventType::DealStageChanged);
    }

    public function test_creating_a_deal_does_not_queue_a_stage_changed_webhook(): void
    {
        Queue::fake();
        $client = ExternalApiClient::factory()->create();
        WebhookSubscription::factory()->create([
            'external_api_client_id' => $client->id,
            'event_type' => WebhookEventType::DealStageChanged,
        ]);

        Deal::create([
            'title' => 'فروش بسته VIP',
            'stage' => DealStage::NewLead,
            'external_api_client_id' => $client->id,
        ]);

        Queue::assertNotPushed(DispatchWebhookJob::class);
    }
}
