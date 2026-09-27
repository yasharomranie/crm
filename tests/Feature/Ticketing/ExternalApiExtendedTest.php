<?php

namespace Tests\Feature\Ticketing;

use App\Domain\Ticketing\TicketIssuer;
use App\Enums\AdmissionTicketStatus;
use App\Enums\TicketSourceType;
use App\Models\AdmissionTicket;
use App\Models\DiscountCode;
use App\Models\ExternalApiClient;
use App\Models\Gate;
use App\Models\GateRule;
use App\Models\Refund;
use App\Models\TicketVariant;
use App\Models\TicketVariantPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the newer external-API endpoints (discount validation, refund,
 * standalone-scanner gate scan) - independent of ExternalApiTest, which
 * already covers auth/idempotency/permission gating in general.
 */
class ExternalApiExtendedTest extends TestCase
{
    use RefreshDatabase;

    private function headers(ExternalApiClient $client, string $secret = 'test-secret'): array
    {
        return ['X-API-Key' => $client->api_key, 'X-API-Secret' => $secret];
    }

    private function variantWithPrice(int $amount = 100000): TicketVariant
    {
        $variant = TicketVariant::factory()->create();
        TicketVariantPrice::factory()->create(['ticket_variant_id' => $variant->id, 'amount' => $amount]);

        return $variant;
    }

    private function issueTicket(TicketVariant $variant, ?ExternalApiClient $client = null): AdmissionTicket
    {
        $order = app(TicketIssuer::class)->issue([
            'source_type' => TicketSourceType::ExternalPos,
            'items' => [['ticket_variant_id' => $variant->id, 'quantity' => 1]],
            'external_api_client_id' => $client?->id,
        ]);

        return $order->items->first()->tickets->first();
    }

    public function test_a_valid_discount_code_returns_the_computed_amounts(): void
    {
        $variant = $this->variantWithPrice(200000);
        DiscountCode::factory()->create(['code' => 'SAVE10', 'discount_value' => 10]);
        $client = ExternalApiClient::factory()->create();

        $response = $this->postJson('/api/v1/external/discount-codes/validate', [
            'code' => 'SAVE10',
            'ticket_variant_id' => $variant->id,
        ], $this->headers($client));

        $response->assertOk();
        $this->assertTrue($response->json('valid'));
        $this->assertSame(200000, $response->json('subtotal'));
        $this->assertSame(20000, $response->json('discount_amount'));
        $this->assertSame(180000, $response->json('final_amount'));
    }

    public function test_an_expired_discount_code_is_reported_as_invalid_with_a_reason(): void
    {
        $variant = $this->variantWithPrice();
        DiscountCode::factory()->create(['code' => 'OLD10', 'end_date' => now()->subDay()]);
        $client = ExternalApiClient::factory()->create();

        $response = $this->postJson('/api/v1/external/discount-codes/validate', [
            'code' => 'OLD10',
            'ticket_variant_id' => $variant->id,
        ], $this->headers($client));

        $response->assertOk();
        $this->assertFalse($response->json('valid'));
        $this->assertNotEmpty($response->json('reason'));
    }

    public function test_an_unknown_discount_code_is_not_found(): void
    {
        $variant = $this->variantWithPrice();
        $client = ExternalApiClient::factory()->create();

        $this->postJson('/api/v1/external/discount-codes/validate', [
            'code' => 'NOPE',
            'ticket_variant_id' => $variant->id,
        ], $this->headers($client))->assertStatus(404);
    }

    public function test_refunding_a_ticket_marks_it_refunded_and_records_a_refund(): void
    {
        $variant = $this->variantWithPrice(150000);
        $client = ExternalApiClient::factory()->create();
        $ticket = $this->issueTicket($variant, $client);

        $response = $this->postJson("/api/v1/external/tickets/{$ticket->ticket_number}/refund", [
            'reason' => 'انصراف مشتری',
        ], [...$this->headers($client), 'Idempotency-Key' => 'refund-1']);

        $response->assertStatus(201);
        $this->assertSame(150000, $response->json('amount'));
        $this->assertSame(AdmissionTicketStatus::Refunded, $ticket->fresh()->status);
        $this->assertSame(1, Refund::count());
    }

    public function test_refunding_an_already_refunded_ticket_is_rejected(): void
    {
        $variant = $this->variantWithPrice();
        $client = ExternalApiClient::factory()->create();
        $ticket = $this->issueTicket($variant, $client);
        $ticket->update(['status' => AdmissionTicketStatus::Refunded]);

        $response = $this->postJson("/api/v1/external/tickets/{$ticket->ticket_number}/refund", [], [
            ...$this->headers($client), 'Idempotency-Key' => 'refund-2',
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, Refund::count());
    }

    public function test_a_client_cannot_refund_a_ticket_belonging_to_a_different_client(): void
    {
        $variant = $this->variantWithPrice();
        $owner = ExternalApiClient::factory()->create();
        $stranger = ExternalApiClient::factory()->create();
        $ticket = $this->issueTicket($variant, $owner);

        $this->postJson("/api/v1/external/tickets/{$ticket->ticket_number}/refund", [], [
            ...$this->headers($stranger), 'Idempotency-Key' => 'refund-3',
        ])->assertStatus(404);
    }

    public function test_a_standalone_scanner_can_validate_a_ticket_through_the_api(): void
    {
        $variant = $this->variantWithPrice();
        $gate = Gate::factory()->create();
        GateRule::factory()->create(['gate_id' => $gate->id, 'ticket_variant_id' => $variant->id]);
        $ticket = $this->issueTicket($variant);
        $client = ExternalApiClient::factory()->create();

        $response = $this->postJson("/api/v1/external/gates/{$gate->code}/scan", [
            'qr_token' => $ticket->qrToken->token,
        ], $this->headers($client));

        $response->assertOk();
        $this->assertSame('success', $response->json('result'));
        $this->assertSame($ticket->ticket_number, $response->json('ticket_number'));
        $this->assertSame(AdmissionTicketStatus::Used, $ticket->fresh()->status);
    }

    public function test_scanning_with_an_invalid_qr_token_is_rejected(): void
    {
        $gate = Gate::factory()->create();
        $client = ExternalApiClient::factory()->create();

        $response = $this->postJson("/api/v1/external/gates/{$gate->code}/scan", [
            'qr_token' => 'does-not-exist',
        ], $this->headers($client));

        $response->assertOk();
        $this->assertSame('rejected', $response->json('result'));
    }

    public function test_scanning_at_an_unknown_gate_code_is_not_found(): void
    {
        $client = ExternalApiClient::factory()->create();

        $this->postJson('/api/v1/external/gates/NOPE/scan', [
            'qr_token' => 'whatever',
        ], $this->headers($client))->assertStatus(404);
    }
}
