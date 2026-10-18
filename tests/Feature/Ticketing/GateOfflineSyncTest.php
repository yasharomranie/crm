<?php

namespace Tests\Feature\Ticketing;

use App\Domain\Ticketing\TicketIssuer;
use App\Enums\AdmissionTicketStatus;
use App\Enums\TicketSourceType;
use App\Models\Gate;
use App\Models\GateOfflineScanSync;
use App\Models\GateRule;
use App\Models\TicketUsage;
use App\Models\TicketVariant;
use App\Models\TicketVariantPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Covers the Gate Scanner's offline mode: the browser pulls a snapshot of
 * usable tickets via sync-package while online, then - once reconnected -
 * pushes the scans it recorded offline through sync-scans, which replays
 * each one through the real GateValidationService (never trusts the
 * browser's own offline guess) and is idempotent against retries.
 */
class GateOfflineSyncTest extends TestCase
{
    use RefreshDatabase;

    private function operator(): User
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'gate-operator']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'gate-scanner.access', 'guard_name' => 'web']));
        $user->assignRole($role);

        return $user;
    }

    private function variantWithPrice(int $amount = 100000): TicketVariant
    {
        $variant = TicketVariant::factory()->create();
        TicketVariantPrice::factory()->create(['ticket_variant_id' => $variant->id, 'amount' => $amount]);

        return $variant;
    }

    private function issueTicket(TicketVariant $variant)
    {
        $order = app(TicketIssuer::class)->issue([
            'source_type' => TicketSourceType::Cashier,
            'items' => [['ticket_variant_id' => $variant->id, 'quantity' => 1]],
        ]);

        return $order->items->first()->tickets->first();
    }

    public function test_a_user_without_gate_scanner_access_cannot_pull_or_push(): void
    {
        // A role-less user gets this app's backward-compatible unrestricted
        // access (see AppServiceProvider's Gate::before) - an actual
        // restricted role, with some other permission but not this one, is
        // required to exercise the negative case.
        $stranger = User::factory()->create();
        $stranger->assignRole(Role::firstOrCreate(['name' => 'cashier']));
        $gate = Gate::factory()->create();

        $this->actingAs($stranger)->getJson(route('gate-scanner.sync-package', $gate))->assertForbidden();
        $this->actingAs($stranger)->postJson(route('gate-scanner.sync-scans', $gate), ['scans' => []])->assertForbidden();
    }

    public function test_sync_package_includes_only_currently_usable_tickets(): void
    {
        $variant = $this->variantWithPrice();
        $gate = Gate::factory()->create();

        $usable = $this->issueTicket($variant);
        $cancelled = $this->issueTicket($variant);
        $cancelled->update(['status' => AdmissionTicketStatus::Cancelled]);
        $expired = $this->issueTicket($variant);
        $expired->update(['valid_from' => now()->subDays(2), 'valid_until' => now()->subDay()]);
        $revoked = $this->issueTicket($variant);
        $revoked->qrToken->update(['revoked_at' => now()]);

        $response = $this->actingAs($this->operator())
            ->getJson(route('gate-scanner.sync-package', $gate))
            ->assertSuccessful();

        $tokens = collect($response->json('tickets'))->pluck('token');

        $this->assertContains($usable->qrToken->token, $tokens);
        $this->assertNotContains($cancelled->qrToken->token, $tokens);
        $this->assertNotContains($expired->qrToken->token, $tokens);
        $this->assertNotContains($revoked->qrToken->token, $tokens);
    }

    public function test_pushing_a_valid_offline_scan_consumes_the_ticket_for_real(): void
    {
        $variant = TicketVariant::factory()->outbound()->create();
        TicketVariantPrice::factory()->create(['ticket_variant_id' => $variant->id, 'amount' => 100000]);
        $gate = Gate::factory()->create();
        GateRule::factory()->create(['gate_id' => $gate->id, 'ticket_variant_id' => $variant->id, 'direction' => 'outbound']);
        $ticket = $this->issueTicket($variant);

        $response = $this->actingAs($this->operator())
            ->postJson(route('gate-scanner.sync-scans', $gate), [
                'scans' => [[
                    'client_scan_id' => (string) Str::uuid(),
                    'qr_token' => $ticket->qrToken->token,
                    'scanned_at' => now()->toIso8601String(),
                    'local_result' => 'success',
                ]],
            ])
            ->assertSuccessful();

        $response->assertJsonPath('results.0.result', 'success');
        $response->assertJsonPath('results.0.reconciled', true);
        $this->assertSame(1, TicketUsage::where('admission_ticket_id', $ticket->id)->count());
        $this->assertSame(1, GateOfflineScanSync::count());
    }

    public function test_pushing_the_same_client_scan_id_twice_does_not_double_consume(): void
    {
        $variant = TicketVariant::factory()->outbound()->create();
        TicketVariantPrice::factory()->create(['ticket_variant_id' => $variant->id, 'amount' => 100000]);
        $gate = Gate::factory()->create();
        GateRule::factory()->create(['gate_id' => $gate->id, 'ticket_variant_id' => $variant->id, 'direction' => 'outbound']);
        $ticket = $this->issueTicket($variant);
        $operator = $this->operator();

        $payload = [
            'scans' => [[
                'client_scan_id' => 'same-client-id',
                'qr_token' => $ticket->qrToken->token,
                'scanned_at' => now()->toIso8601String(),
                'local_result' => 'success',
            ]],
        ];

        $first = $this->actingAs($operator)->postJson(route('gate-scanner.sync-scans', $gate), $payload)->assertSuccessful();
        $second = $this->actingAs($operator)->postJson(route('gate-scanner.sync-scans', $gate), $payload)->assertSuccessful();

        $this->assertSame('success', $first->json('results.0.result'));
        $this->assertSame('success', $second->json('results.0.result'));
        // A genuine second real scan of a one-time ticket would be
        // "already_used" - this must stay "success" both times because
        // it's a retried push of the exact same offline event, not two
        // different scans.
        $this->assertSame(1, TicketUsage::where('admission_ticket_id', $ticket->id)->count());
        $this->assertSame(1, GateOfflineScanSync::count());
    }

    public function test_a_ticket_scanned_offline_at_two_gates_reconciles_only_the_winner(): void
    {
        $variant = TicketVariant::factory()->outbound()->create();
        TicketVariantPrice::factory()->create(['ticket_variant_id' => $variant->id, 'amount' => 100000]);
        $gateA = Gate::factory()->create();
        $gateB = Gate::factory()->create();
        GateRule::factory()->create(['gate_id' => $gateA->id, 'ticket_variant_id' => $variant->id, 'direction' => 'outbound']);
        GateRule::factory()->create(['gate_id' => $gateB->id, 'ticket_variant_id' => $variant->id, 'direction' => 'outbound']);
        $ticket = $this->issueTicket($variant);
        $ticket->update(['valid_from' => now()->subHour()]);
        $operator = $this->operator();

        // Both gates were offline and each locally accepted the same
        // one-time ticket - gate A's queued scan happened first.
        $earlier = now()->subMinute()->toIso8601String();
        $later = now()->toIso8601String();

        $resultA = $this->actingAs($operator)->postJson(route('gate-scanner.sync-scans', $gateA), [
            'scans' => [['client_scan_id' => 'a-1', 'qr_token' => $ticket->qrToken->token, 'scanned_at' => $earlier, 'local_result' => 'success']],
        ])->assertSuccessful();

        $resultB = $this->actingAs($operator)->postJson(route('gate-scanner.sync-scans', $gateB), [
            'scans' => [['client_scan_id' => 'b-1', 'qr_token' => $ticket->qrToken->token, 'scanned_at' => $later, 'local_result' => 'success']],
        ])->assertSuccessful();

        $this->assertSame('success', $resultA->json('results.0.result'));
        $this->assertTrue($resultA->json('results.0.reconciled'));

        $this->assertSame('rejected', $resultB->json('results.0.result'));
        $this->assertFalse($resultB->json('results.0.reconciled'), 'gate B guessed success offline but the real (later) outcome is a rejection - must be flagged for staff review');
        $this->assertSame(1, TicketUsage::where('admission_ticket_id', $ticket->id)->count());
    }
}
