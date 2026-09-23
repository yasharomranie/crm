<?php

namespace Tests\Feature\Ticketing;

use App\Models\AdmissionTicket;
use App\Models\ExternalApiClient;
use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\Product;
use App\Models\TicketVariant;
use App\Models\TicketVariantPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers section 101-104: the external POS/online-sales API's own
 * authentication, per-operation permission gate, product allow-list, and
 * idempotency guarantees - independent of the ticket-issuing logic itself
 * (already covered by GateValidationTest/PricingAndTemplateSnapshotTest).
 */
class ExternalApiTest extends TestCase
{
    use RefreshDatabase;

    private function variantWithPrice(int $amount = 100000): TicketVariant
    {
        $variant = TicketVariant::factory()->create();
        TicketVariantPrice::factory()->create(['ticket_variant_id' => $variant->id, 'amount' => $amount]);

        return $variant;
    }

    private function headers(ExternalApiClient $client, string $secret = 'test-secret'): array
    {
        return ['X-API-Key' => $client->api_key, 'X-API-Secret' => $secret];
    }

    public function test_a_request_without_api_credentials_is_rejected(): void
    {
        $this->getJson('/api/v1/external/products')->assertStatus(401);
    }

    public function test_a_request_with_a_wrong_api_secret_is_rejected(): void
    {
        $client = ExternalApiClient::factory()->create();

        $this->getJson('/api/v1/external/products', $this->headers($client, 'wrong-secret'))
            ->assertStatus(401);
    }

    public function test_a_request_from_an_inactive_client_is_rejected(): void
    {
        $client = ExternalApiClient::factory()->create(['is_active' => false]);

        $this->getJson('/api/v1/external/products', $this->headers($client))
            ->assertStatus(401);
    }

    public function test_a_client_without_the_required_operation_permission_is_forbidden(): void
    {
        $client = ExternalApiClient::factory()->create(['allowed_operations' => ['ticket.issue']]);

        $this->getJson('/api/v1/external/products', $this->headers($client))
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_a_client_exceeding_its_rate_limit_is_throttled(): void
    {
        $client = ExternalApiClient::factory()->create(['rate_limit_per_minute' => 1]);

        $this->getJson('/api/v1/external/products', $this->headers($client))->assertSuccessful();
        $this->getJson('/api/v1/external/products', $this->headers($client))->assertStatus(429);
    }

    public function test_issuing_a_ticket_requires_the_idempotency_key_header(): void
    {
        $variant = $this->variantWithPrice();
        $client = ExternalApiClient::factory()->create();

        $this->postJson('/api/v1/external/tickets', [
            'product_id' => $variant->product_id,
            'ticket_variant_id' => $variant->id,
        ], $this->headers($client))->assertStatus(400);
    }

    public function test_a_client_can_issue_a_ticket_and_receive_a_qr_payload(): void
    {
        $variant = $this->variantWithPrice(150000);
        $client = ExternalApiClient::factory()->create();

        $response = $this->postJson('/api/v1/external/tickets', [
            'product_id' => $variant->product_id,
            'ticket_variant_id' => $variant->id,
        ], [...$this->headers($client), 'Idempotency-Key' => 'req-1']);

        $response->assertStatus(201);
        $ticket = $response->json('tickets.0');
        $this->assertNotEmpty($ticket['qr_payload']);
        $this->assertNotEmpty($ticket['ticket_number']);
        $this->assertSame(1, Order::count());
    }

    public function test_a_client_cannot_issue_a_ticket_for_a_product_it_is_not_allowed_to_sell(): void
    {
        $allowedProduct = Product::factory()->create();
        $blockedVariant = $this->variantWithPrice();
        $client = ExternalApiClient::factory()->create();
        $client->allowedProducts()->attach($allowedProduct);

        $response = $this->postJson('/api/v1/external/tickets', [
            'product_id' => $blockedVariant->product_id,
            'ticket_variant_id' => $blockedVariant->id,
        ], [...$this->headers($client), 'Idempotency-Key' => 'req-1']);

        $response->assertStatus(403)->assertJsonPath('error.code', 'FORBIDDEN');
        $this->assertSame(0, Order::count());
    }

    public function test_a_client_without_ticket_issue_permission_is_forbidden(): void
    {
        $variant = $this->variantWithPrice();
        $client = ExternalApiClient::factory()->create(['allowed_operations' => ['product.read']]);

        $response = $this->postJson('/api/v1/external/tickets', [
            'product_id' => $variant->product_id,
            'ticket_variant_id' => $variant->id,
        ], [...$this->headers($client), 'Idempotency-Key' => 'req-1']);

        $response->assertStatus(403);
        $this->assertSame(0, Order::count());
    }

    public function test_retrying_the_same_idempotency_key_with_the_same_payload_replays_the_original_response_without_a_new_order(): void
    {
        $variant = $this->variantWithPrice();
        $client = ExternalApiClient::factory()->create();
        $payload = ['product_id' => $variant->product_id, 'ticket_variant_id' => $variant->id];
        $headers = [...$this->headers($client), 'Idempotency-Key' => 'same-key'];

        $first = $this->postJson('/api/v1/external/tickets', $payload, $headers);
        $second = $this->postJson('/api/v1/external/tickets', $payload, $headers);

        $first->assertStatus(201);
        $second->assertStatus(201);
        $this->assertSame($first->json('order_id'), $second->json('order_id'));
        $this->assertSame(1, Order::count());
        $this->assertSame(1, IdempotencyKey::count());
    }

    public function test_reusing_an_idempotency_key_with_a_different_payload_is_a_conflict(): void
    {
        $variantOne = $this->variantWithPrice();
        $variantTwo = $this->variantWithPrice();
        $client = ExternalApiClient::factory()->create();
        $headers = [...$this->headers($client), 'Idempotency-Key' => 'same-key'];

        $this->postJson('/api/v1/external/tickets', [
            'product_id' => $variantOne->product_id,
            'ticket_variant_id' => $variantOne->id,
        ], $headers)->assertStatus(201);

        $response = $this->postJson('/api/v1/external/tickets', [
            'product_id' => $variantTwo->product_id,
            'ticket_variant_id' => $variantTwo->id,
        ], $headers);

        $response->assertStatus(409)->assertJsonPath('error.code', 'CONFLICT');
        $this->assertSame(1, Order::count());
    }

    public function test_a_client_cannot_read_a_ticket_issued_to_a_different_client(): void
    {
        $variant = $this->variantWithPrice();
        $owner = ExternalApiClient::factory()->create();
        $stranger = ExternalApiClient::factory()->create();

        $issued = $this->postJson('/api/v1/external/tickets', [
            'product_id' => $variant->product_id,
            'ticket_variant_id' => $variant->id,
        ], [...$this->headers($owner), 'Idempotency-Key' => 'req-1']);

        $ticketNumber = $issued->json('tickets.0.ticket_number');

        $this->getJson("/api/v1/external/tickets/{$ticketNumber}", $this->headers($stranger))
            ->assertStatus(404);

        $this->getJson("/api/v1/external/tickets/{$ticketNumber}", $this->headers($owner))
            ->assertSuccessful();
    }

    public function test_reprinting_via_the_api_never_creates_a_new_ticket(): void
    {
        $variant = $this->variantWithPrice();
        $client = ExternalApiClient::factory()->create([
            'allowed_operations' => ['product.read', 'ticket_variant.read', 'ticket.issue', 'ticket.read', 'ticket.reprint'],
        ]);

        $issued = $this->postJson('/api/v1/external/tickets', [
            'product_id' => $variant->product_id,
            'ticket_variant_id' => $variant->id,
        ], [...$this->headers($client), 'Idempotency-Key' => 'req-1']);

        $ticketNumber = $issued->json('tickets.0.ticket_number');

        $reprint = $this->postJson("/api/v1/external/tickets/{$ticketNumber}/reprint", [], $this->headers($client));

        $reprint->assertSuccessful();
        $this->assertSame($ticketNumber, $reprint->json('ticket_number'));
        $this->assertSame(1, AdmissionTicket::where('ticket_number', $ticketNumber)->count());
    }
}
