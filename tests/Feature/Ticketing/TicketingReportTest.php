<?php

namespace Tests\Feature\Ticketing;

use App\Domain\Ticketing\GateValidationService;
use App\Domain\Ticketing\TicketingReport;
use App\Domain\Ticketing\TicketIssuer;
use App\Enums\DiscountType;
use App\Enums\PaymentMethod;
use App\Enums\TicketSourceType;
use App\Filament\Pages\TicketingReportsPage;
use App\Models\DiscountCode;
use App\Models\ExternalApiClient;
use App\Models\Gate;
use App\Models\GateRule;
use App\Models\Guest;
use App\Models\Order;
use App\Models\Product;
use App\Models\TicketVariant;
use App\Models\TicketVariantPrice;
use App\Models\User;
use App\Models\WalletTopup;
use App\Models\WebhookSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TicketingReportTest extends TestCase
{
    use RefreshDatabase;

    private function variant(int $amount, ?Product $product = null): TicketVariant
    {
        $variant = TicketVariant::factory()->create($product ? ['product_id' => $product->id] : []);
        TicketVariantPrice::factory()->create(['ticket_variant_id' => $variant->id, 'amount' => $amount]);

        return $variant;
    }

    private function sell(TicketVariant $variant, int $quantity = 1, array $extra = []): Order
    {
        return app(TicketIssuer::class)->issue([
            'source_type' => TicketSourceType::Cashier,
            'items' => [['ticket_variant_id' => $variant->id, 'quantity' => $quantity]],
            ...$extra,
        ]);
    }

    private function report(): array
    {
        return app(TicketingReport::class)->build(now()->startOfDay(), now()->addMinute());
    }

    public function test_sales_are_totalled_by_product_and_free_tickets_are_not_sales(): void
    {
        $cableCar = Product::factory()->create(['name' => 'تله‌کابین']);
        $this->sell($this->variant(100_000, $cableCar), 2);
        $this->sell($this->variant(50_000), 1);
        $manager = User::factory()->create();
        app(TicketIssuer::class)->issueComplimentary($cableCar, $this->variant(100_000, $cableCar), 3, 'مهمان استاندار', $manager);

        $report = $this->report();

        $this->assertSame(2, $report['sales']['orders']);
        $this->assertSame(3, $report['sales']['tickets']);
        $this->assertSame(250_000, $report['sales']['net']);
        $this->assertSame('تله‌کابین', $report['sales']['by_product'][0]['name']);
        $this->assertSame(200_000, $report['sales']['by_product'][0]['net']);
        $this->assertSame(3, $report['complimentary']['tickets']);
        $this->assertSame(300_000, $report['complimentary']['nominal_value']);
        $this->assertSame('مهمان استاندار', $report['complimentary']['by_reason'][0]['reason']);
    }

    public function test_sales_are_split_by_cashier_and_payment_method(): void
    {
        $cashier = User::factory()->create(['name' => 'صندوقدار اول']);
        $order = $this->sell($this->variant(80_000), 1, ['created_by' => $cashier->id]);
        $order->payments()->create(['method' => PaymentMethod::Cash, 'amount' => 80_000, 'status' => 'paid', 'paid_at' => now()]);

        $report = $this->report();

        $this->assertSame('صندوقدار اول', $report['sales']['by_cashier'][0]['name']);
        $this->assertSame(80_000, $report['sales']['by_cashier'][0]['net']);
        $this->assertSame(['method' => PaymentMethod::Cash->getLabel(), 'amount' => 80_000], $report['sales']['by_method'][0]);
    }

    public function test_the_discount_report_shows_what_each_code_cost(): void
    {
        $code = DiscountCode::factory()->create(['code' => 'NOWRUZ', 'discount_type' => DiscountType::Percentage, 'discount_value' => 25]);
        $this->sell($this->variant(100_000), 2, ['discount_code' => $code->code]);

        $row = $this->report()['discounts'][0];

        $this->assertSame('NOWRUZ', $row['code']);
        $this->assertSame(50_000, $row['discount']);
        $this->assertSame(150_000, $row['net']);
    }

    public function test_expired_unused_tickets_are_counted_with_their_value(): void
    {
        $order = $this->sell($this->variant(100_000), 2);
        $tickets = $order->items->first()->tickets;
        $tickets->each->update(['valid_from' => now()->subDays(3), 'valid_until' => now()->subDay()]);
        // One of the two was used before it expired.
        $tickets->first()->update(['used_uses' => 1]);

        $unused = $this->report()['unused'];

        $this->assertSame(1, $unused['tickets']);
        $this->assertSame(100_000, $unused['value']);
    }

    public function test_entry_flow_counts_successful_passes_by_hour(): void
    {
        $variant = $this->variant(10_000);
        $gate = Gate::factory()->create();
        GateRule::factory()->create(['gate_id' => $gate->id, 'ticket_variant_id' => $variant->id]);
        $ticket = $this->sell($variant)->items->first()->tickets->first();
        app(GateValidationService::class)->scan($ticket->qrToken->token, $gate, null);

        $byHour = $this->report()['entry_by_hour'];

        $this->assertSame(1, array_sum($byHour));
        $this->assertSame(1, $byHour[(int) now()->setTimezone(config('app.timezone'))->format('G')]);
    }

    public function test_reconciliation_flags_online_payments_without_a_reference_and_stuck_topups(): void
    {
        $order = $this->sell($this->variant(10_000));
        $order->payments()->create(['method' => PaymentMethod::Online, 'amount' => 10_000, 'status' => 'paid', 'paid_at' => now()]);
        $guest = Guest::factory()->create();
        WalletTopup::create(['guest_id' => $guest->id, 'amount' => 5_000, 'authority' => 'A1'])->forceFill(['created_at' => now()->subHours(3)])->save();
        WalletTopup::create(['guest_id' => $guest->id, 'amount' => 5_000, 'authority' => 'A2', 'status' => 'failed']);
        WebhookSubscription::query()->insert([
            'external_api_client_id' => ExternalApiClient::factory()->create()->id, 'event_type' => 'ticket.scanned',
            'target_url' => 'https://example.test', 'signing_secret' => 'x', 'is_active' => true, 'consecutive_failures' => 3,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $reconciliation = $this->report()['reconciliation'];

        $this->assertSame($order->order_number, $reconciliation['online_without_reference'][0]['order_number']);
        $this->assertSame(1, $reconciliation['stuck_topups']);
        $this->assertSame(1, $reconciliation['failed_topups']);
        $this->assertSame(1, $reconciliation['failing_webhooks']);
    }

    public function test_the_page_renders_for_an_admin_and_switches_period(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));
        $this->sell($this->variant(10_000));

        Livewire::actingAs($admin)->test(TicketingReportsPage::class)
            ->assertSee('فروش خالص')
            ->set('period', '30')
            ->assertSee('فروش خالص');
    }
}
