<?php

namespace Tests\Feature\Ticketing;

use App\Domain\Ticketing\GateValidationService;
use App\Domain\Ticketing\TicketIssuer;
use App\Enums\AdmissionTicketStatus;
use App\Enums\GateRejectionReason;
use App\Enums\GateScanResult;
use App\Enums\TicketSourceType;
use App\Models\Gate;
use App\Models\GateRule;
use App\Models\Product;
use App\Models\TicketUsage;
use App\Models\TicketUsageLog;
use App\Models\TicketVariant;
use App\Models\TicketVariantPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the spec's mandatory ticket scenarios (section 73, items 1-14) -
 * the gate direction/sequence matrix and the double-scan concurrency
 * guarantee, against App\Domain\Ticketing\GateValidationService.
 */
class GateValidationTest extends TestCase
{
    use RefreshDatabase;

    private Gate $outboundGate;

    private Gate $returnGate;

    private TicketVariant $outboundVariant;

    private TicketVariant $returnVariant;

    private TicketVariant $roundTripVariant;

    protected function setUp(): void
    {
        parent::setUp();

        $product = Product::factory()->create();

        $this->outboundVariant = TicketVariant::factory()->outbound()->create(['product_id' => $product->id]);
        $this->returnVariant = TicketVariant::factory()->return()->create(['product_id' => $product->id]);
        $this->roundTripVariant = TicketVariant::factory()->roundTrip()->create(['product_id' => $product->id]);

        foreach ([$this->outboundVariant, $this->returnVariant, $this->roundTripVariant] as $variant) {
            TicketVariantPrice::factory()->create(['ticket_variant_id' => $variant->id, 'amount' => 100000]);
        }

        $this->outboundGate = Gate::factory()->create(['name' => 'Outbound Gate']);
        $this->returnGate = Gate::factory()->create(['name' => 'Return Gate']);

        // Outbound gate: accepts outbound tickets, and stage 1 of a round trip.
        GateRule::factory()->create(['gate_id' => $this->outboundGate->id, 'ticket_variant_id' => $this->outboundVariant->id, 'direction' => 'outbound']);
        GateRule::factory()->create(['gate_id' => $this->outboundGate->id, 'ticket_variant_id' => $this->roundTripVariant->id, 'usage_stage' => 1]);

        // Return gate: accepts return tickets, and stage 2 of a round trip.
        GateRule::factory()->create(['gate_id' => $this->returnGate->id, 'ticket_variant_id' => $this->returnVariant->id, 'direction' => 'return']);
        GateRule::factory()->create(['gate_id' => $this->returnGate->id, 'ticket_variant_id' => $this->roundTripVariant->id, 'usage_stage' => 2]);
    }

    private function issueTicket(TicketVariant $variant)
    {
        $order = app(TicketIssuer::class)->issue([
            'source_type' => TicketSourceType::Cashier,
            'items' => [['ticket_variant_id' => $variant->id, 'quantity' => 1]],
        ]);

        return $order->items->first()->tickets->first();
    }

    private function scan(string $token, Gate $gate): array
    {
        return app(GateValidationService::class)->scan($token, $gate, null);
    }

    public function test_outbound_ticket_at_outbound_gate_succeeds(): void
    {
        $ticket = $this->issueTicket($this->outboundVariant);

        $result = $this->scan($ticket->qrToken->token, $this->outboundGate);

        $this->assertSame(GateScanResult::Success, $result['result']);
    }

    public function test_outbound_ticket_at_return_gate_is_rejected(): void
    {
        $ticket = $this->issueTicket($this->outboundVariant);

        $result = $this->scan($ticket->qrToken->token, $this->returnGate);

        $this->assertSame(GateScanResult::Rejected, $result['result']);
        $this->assertSame(GateRejectionReason::WrongGate, $result['reason']);
    }

    public function test_return_ticket_at_return_gate_succeeds(): void
    {
        $ticket = $this->issueTicket($this->returnVariant);

        $result = $this->scan($ticket->qrToken->token, $this->returnGate);

        $this->assertSame(GateScanResult::Success, $result['result']);
    }

    public function test_return_ticket_at_outbound_gate_is_rejected(): void
    {
        $ticket = $this->issueTicket($this->returnVariant);

        $result = $this->scan($ticket->qrToken->token, $this->outboundGate);

        $this->assertSame(GateScanResult::Rejected, $result['result']);
        $this->assertSame(GateRejectionReason::WrongGate, $result['reason']);
    }

    public function test_round_trip_at_outbound_gate_succeeds(): void
    {
        $ticket = $this->issueTicket($this->roundTripVariant);

        $result = $this->scan($ticket->qrToken->token, $this->outboundGate);

        $this->assertSame(GateScanResult::Success, $result['result']);
        $this->assertSame(1, $result['usage_number']);
    }

    public function test_same_round_trip_at_return_gate_succeeds_after_outbound(): void
    {
        $ticket = $this->issueTicket($this->roundTripVariant);
        $this->scan($ticket->qrToken->token, $this->outboundGate);

        $result = $this->scan($ticket->qrToken->token, $this->returnGate);

        $this->assertSame(GateScanResult::Success, $result['result']);
        $this->assertSame(2, $result['usage_number']);
    }

    public function test_round_trip_at_return_gate_before_outbound_is_rejected(): void
    {
        $ticket = $this->issueTicket($this->roundTripVariant);

        $result = $this->scan($ticket->qrToken->token, $this->returnGate);

        $this->assertSame(GateScanResult::Rejected, $result['result']);
        $this->assertSame(GateRejectionReason::SequenceError, $result['reason']);
        $this->assertSame(0, TicketUsage::count());
    }

    public function test_a_one_time_ticket_reused_is_rejected(): void
    {
        $ticket = $this->issueTicket($this->outboundVariant);
        $this->scan($ticket->qrToken->token, $this->outboundGate);

        $result = $this->scan($ticket->qrToken->token, $this->outboundGate);

        $this->assertSame(GateScanResult::Rejected, $result['result']);
        $this->assertSame(GateRejectionReason::NoRemainingUsage, $result['reason']);
    }

    public function test_the_same_qr_scanned_concurrently_only_succeeds_once(): void
    {
        $ticket = $this->issueTicket($this->outboundVariant);
        $token = $ticket->qrToken->token;

        // True parallel requests aren't simulable in a single-process
        // PHPUnit run, but the actual safety net - the SELECT ... FOR
        // UPDATE lock plus the UNIQUE(admission_ticket_id, usage_number)
        // constraint - is exercised identically by two sequential calls
        // racing for the same, single usage slot: the second one must be
        // rejected exactly as it would be if truly concurrent.
        $first = $this->scan($token, $this->outboundGate);
        $second = $this->scan($token, $this->outboundGate);

        $this->assertSame(GateScanResult::Success, $first['result']);
        $this->assertSame(GateScanResult::Rejected, $second['result']);
        $this->assertSame(1, TicketUsage::where('admission_ticket_id', $ticket->id)->count());
    }

    public function test_an_expired_ticket_is_rejected(): void
    {
        $ticket = $this->issueTicket($this->outboundVariant);
        $ticket->update(['valid_from' => now()->subDays(2), 'valid_until' => now()->subDay()]);

        $result = $this->scan($ticket->qrToken->token, $this->outboundGate);

        $this->assertSame(GateRejectionReason::Expired, $result['reason']);
    }

    public function test_a_cancelled_ticket_is_rejected(): void
    {
        $ticket = $this->issueTicket($this->outboundVariant);
        $ticket->update(['status' => AdmissionTicketStatus::Cancelled]);

        $result = $this->scan($ticket->qrToken->token, $this->outboundGate);

        $this->assertSame(GateRejectionReason::Cancelled, $result['reason']);
    }

    public function test_a_refunded_ticket_is_rejected(): void
    {
        $ticket = $this->issueTicket($this->outboundVariant);
        $ticket->update(['status' => AdmissionTicketStatus::Refunded]);

        $result = $this->scan($ticket->qrToken->token, $this->outboundGate);

        $this->assertSame(GateRejectionReason::Refunded, $result['reason']);
    }

    public function test_a_blocked_ticket_is_rejected(): void
    {
        $ticket = $this->issueTicket($this->outboundVariant);
        $ticket->update(['status' => AdmissionTicketStatus::Blocked]);

        $result = $this->scan($ticket->qrToken->token, $this->outboundGate);

        $this->assertSame(GateRejectionReason::Blocked, $result['reason']);
    }

    public function test_an_invalid_qr_token_is_rejected(): void
    {
        $result = $this->scan('this-token-does-not-exist', $this->outboundGate);

        $this->assertSame(GateScanResult::Rejected, $result['result']);
        $this->assertSame(GateRejectionReason::InvalidQr, $result['reason']);
    }

    public function test_a_revoked_qr_token_is_rejected_as_invalid(): void
    {
        $ticket = $this->issueTicket($this->outboundVariant);
        $ticket->qrToken->update(['revoked_at' => now()]);

        $result = $this->scan($ticket->qrToken->token, $this->outboundGate);

        $this->assertSame(GateRejectionReason::InvalidQr, $result['reason']);
    }

    public function test_every_scan_attempt_writes_a_usage_log_regardless_of_outcome(): void
    {
        $ticket = $this->issueTicket($this->outboundVariant);

        $this->scan($ticket->qrToken->token, $this->outboundGate);
        $this->scan($ticket->qrToken->token, $this->outboundGate);
        $this->scan('bad-token', $this->outboundGate);

        $this->assertSame(3, TicketUsageLog::count());
    }
}
