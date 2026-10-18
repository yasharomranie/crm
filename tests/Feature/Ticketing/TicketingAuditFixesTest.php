<?php

namespace Tests\Feature\Ticketing;

use App\Domain\Ticketing\GateValidationService;
use App\Domain\Ticketing\RefundService;
use App\Domain\Ticketing\TicketingException;
use App\Domain\Ticketing\TicketIssuer;
use App\Enums\AdmissionTicketStatus;
use App\Enums\DiscountType;
use App\Enums\GateScanResult;
use App\Enums\TicketSourceType;
use App\Models\AdmissionTicket;
use App\Models\DiscountCode;
use App\Models\DiscountUsage;
use App\Models\Gate;
use App\Models\GateRule;
use App\Models\Product;
use App\Models\TicketVariant;
use App\Models\TicketVariantPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression tests for the problems found in the box-office (گیشه بلیت)
 * review: discount arithmetic and abuse, refunds of used tickets, offline
 * scans judged at the wrong time, the offline package size, print access.
 */
class TicketingAuditFixesTest extends TestCase
{
    use RefreshDatabase;

    private function variant(int $amount = 100_000, ?Product $product = null): TicketVariant
    {
        $variant = TicketVariant::factory()->create($product ? ['product_id' => $product->id] : []);
        TicketVariantPrice::factory()->create(['ticket_variant_id' => $variant->id, 'amount' => $amount]);

        return $variant;
    }

    /**
     * @param  list<array{0: TicketVariant, 1: int}>  $lines
     */
    private function issue(array $lines, ?DiscountCode $code = null, ?string $phone = null)
    {
        return app(TicketIssuer::class)->issue([
            'source_type' => TicketSourceType::Cashier,
            'items' => array_map(fn (array $line) => ['ticket_variant_id' => $line[0]->id, 'quantity' => $line[1]], $lines),
            'discount_code' => $code?->code,
            'customer_phone' => $phone,
        ]);
    }

    public function test_a_fixed_discount_is_taken_off_the_order_once_not_per_line(): void
    {
        $a = $this->variant(100_000);
        $b = $this->variant(100_000);
        $code = DiscountCode::factory()->create(['discount_type' => DiscountType::FixedAmount, 'discount_value' => 50_000]);

        $order = $this->issue([[$a, 1], [$b, 1]], $code);

        $this->assertSame(50_000, $order->discount_amount);
        $this->assertSame(150_000, $order->final_amount);
        $this->assertSame(50_000, $order->items->sum('discount_amount'));
    }

    public function test_the_maximum_discount_caps_the_whole_order(): void
    {
        $a = $this->variant(100_000);
        $b = $this->variant(100_000);
        $code = DiscountCode::factory()->create(['discount_type' => DiscountType::Percentage, 'discount_value' => 50, 'maximum_discount_amount' => 30_000]);

        $order = $this->issue([[$a, 1], [$b, 1]], $code);

        $this->assertSame(30_000, $order->discount_amount);
        $this->assertSame(170_000, $order->final_amount);
    }

    public function test_the_discount_is_shared_between_eligible_lines_in_proportion_and_adds_up(): void
    {
        $product = Product::factory()->create();
        $cheap = $this->variant(10_000, $product);
        $dear = $this->variant(30_000, $product);
        $other = $this->variant(100_000);
        $code = DiscountCode::factory()->create(['discount_type' => DiscountType::FixedAmount, 'discount_value' => 10_001]);
        $code->products()->attach($product);

        $order = $this->issue([[$cheap, 1], [$dear, 1], [$other, 1]], $code);

        $this->assertSame(10_001, $order->items->sum('discount_amount'));
        $this->assertSame(0, $order->items->firstWhere('ticket_variant_id', $other->id)->discount_amount);
        $this->assertSame(140_000 - 10_001, $order->final_amount);
    }

    public function test_a_code_that_matches_no_line_discounts_nothing_and_is_not_used_up(): void
    {
        $variant = $this->variant(100_000);
        $code = DiscountCode::factory()->create(['discount_value' => 50, 'usage_limit' => 1]);
        $code->products()->attach(Product::factory()->create());

        $order = $this->issue([[$variant, 1]], $code);

        $this->assertSame(100_000, $order->final_amount);
        $this->assertSame(0, $code->fresh()->used_count);
        $this->assertNull($order->discount_code_id);
    }

    public function test_a_per_customer_limit_cannot_be_dodged_by_leaving_the_phone_empty(): void
    {
        $variant = $this->variant();
        $code = DiscountCode::factory()->create(['per_customer_usage_limit' => 1]);

        $this->expectException(TicketingException::class);

        $this->issue([[$variant, 1]], $code, null);
    }

    public function test_the_same_person_typed_in_another_phone_format_counts_as_one_customer(): void
    {
        $variant = $this->variant();
        $code = DiscountCode::factory()->create(['per_customer_usage_limit' => 1]);
        $this->issue([[$variant, 1]], $code, '09121234567');

        $this->assertSame('09121234567', DiscountUsage::sole()->customer_phone);

        $this->expectException(TicketingException::class);

        $this->issue([[$variant, 1]], $code, '+989121234567');
    }

    public function test_one_line_cannot_issue_an_absurd_number_of_tickets(): void
    {
        $variant = $this->variant();

        $this->expectException(TicketingException::class);

        $this->issue([[$variant, TicketIssuer::MAX_QUANTITY_PER_LINE + 1]]);
    }

    public function test_a_used_ticket_cannot_be_refunded_in_full_but_can_with_an_explicit_amount(): void
    {
        $order = $this->issue([[$this->variant(100_000), 1]]);
        $ticket = $order->items->first()->tickets->first();
        $ticket->update(['status' => AdmissionTicketStatus::Used]);

        try {
            app(RefundService::class)->refundTicket($ticket);
            $this->fail('Expected the full refund to be refused.');
        } catch (TicketingException $exception) {
            $this->assertStringContainsString('استفاده شده', $exception->getMessage());
        }

        $refund = app(RefundService::class)->refundTicket($ticket, amountOverride: 40_000);

        $this->assertSame(40_000, (int) $refund->amount);
    }

    private function openGateForVariant(TicketVariant $variant): Gate
    {
        $gate = Gate::factory()->create();
        GateRule::factory()->create(['gate_id' => $gate->id, 'ticket_variant_id' => $variant->id]);

        return $gate;
    }

    public function test_an_offline_scan_is_judged_at_the_time_it_happened(): void
    {
        $variant = $this->variant();
        $gate = $this->openGateForVariant($variant);
        $ticket = $this->issue([[$variant, 1]])->items->first()->tickets->first();
        $ticket->update(['valid_from' => now()->subDays(2), 'valid_until' => now()->subHour()]);

        $result = app(GateValidationService::class)->scan($ticket->qrToken->token, $gate, null, ['scanned_at' => now()->subHours(2)->toIso8601String()]);

        $this->assertSame(GateScanResult::Success, $result['result']);
    }

    public function test_a_claimed_scan_time_in_the_future_or_too_far_back_is_not_trusted(): void
    {
        $variant = $this->variant();
        $gate = $this->openGateForVariant($variant);
        $expired = $this->issue([[$variant, 1]])->items->first()->tickets->first();
        $expired->update(['valid_from' => now()->subDays(10), 'valid_until' => now()->subDay()]);
        $service = app(GateValidationService::class);

        $future = $service->scan($expired->qrToken->token, $gate, null, ['scanned_at' => now()->addHours(5)->toIso8601String()]);
        $tooOld = $service->scan($expired->qrToken->token, $gate, null, ['scanned_at' => now()->subDays(5)->toIso8601String()]);

        $this->assertSame(GateScanResult::Rejected, $future['result']);
        $this->assertSame(GateScanResult::Rejected, $tooOld['result']);
    }

    public function test_the_offline_package_only_carries_tickets_the_gate_can_admit(): void
    {
        $mine = $this->variant();
        $others = $this->variant();
        $gate = $this->openGateForVariant($mine);
        $wanted = $this->issue([[$mine, 1]])->items->first()->tickets->first();
        $unrelated = $this->issue([[$others, 1]])->items->first()->tickets->first();

        $operator = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'gate-operator']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'gate-scanner.access', 'guard_name' => 'web']));
        $operator->assignRole($role);

        $tokens = collect($this->actingAs($operator)->getJson(route('gate-scanner.sync-package', $gate))->json('tickets'))->pluck('token');

        $this->assertContains($wanted->qrToken->token, $tokens);
        $this->assertNotContains($unrelated->qrToken->token, $tokens);
    }

    public function test_only_people_who_sell_tickets_can_print_one(): void
    {
        $ticket = $this->issue([[$this->variant(), 1]])->items->first()->tickets->first();

        $stranger = User::factory()->create();
        $stranger->assignRole(Role::firstOrCreate(['name' => 'accountant']));
        $this->actingAs($stranger)->get(route('admission-tickets.print', $ticket))->assertForbidden();

        $cashier = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'box-office']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'cashier.access', 'guard_name' => 'web']));
        $cashier->assignRole($role);
        $this->actingAs($cashier)->get(route('admission-tickets.print', $ticket))->assertSuccessful();
    }

    public function test_ticket_numbers_stay_unique(): void
    {
        $order = $this->issue([[$this->variant(), 50]]);

        $numbers = AdmissionTicket::pluck('ticket_number');

        $this->assertSame($numbers->count(), $numbers->unique()->count());
        $this->assertCount(50, $order->items->first()->tickets);
    }
}
