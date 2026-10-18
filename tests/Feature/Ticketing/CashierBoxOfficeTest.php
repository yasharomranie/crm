<?php

namespace Tests\Feature\Ticketing;

use App\Domain\Ticketing\CashierShiftService;
use App\Domain\Ticketing\RefundService;
use App\Domain\Ticketing\TicketingException;
use App\Domain\Ticketing\TicketIssuer;
use App\Enums\PaymentMethod;
use App\Enums\TicketSourceType;
use App\Filament\Pages\Cashier;
use App\Models\CashierShift;
use App\Models\Order;
use App\Models\Payment;
use App\Models\TicketVariant;
use App\Models\TicketVariantPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CashierBoxOfficeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    private function variant(int $amount = 100_000, array $attributes = []): TicketVariant
    {
        $variant = TicketVariant::factory()->create($attributes);
        TicketVariantPrice::factory()->create(['ticket_variant_id' => $variant->id, 'amount' => $amount]);

        return $variant;
    }

    public function test_a_shift_computes_the_cash_that_should_be_in_the_drawer(): void
    {
        $admin = $this->admin();
        $shift = app(CashierShiftService::class)->open($admin, 500_000);
        $variant = $this->variant(100_000);

        Livewire::actingAs($admin)->test(Cashier::class)
            ->call('quickAdd', $variant->id)->set('paymentMethod', 'cash')->call('checkout')
            ->call('quickAdd', $variant->id)->set('paymentMethod', 'card')->call('checkout');

        $summary = app(CashierShiftService::class)->summary($shift->fresh());

        $this->assertSame(200_000, $summary['total_sales']);
        $this->assertSame(100_000, $summary['cash_in']);
        $this->assertSame(600_000, $summary['expected_cash']);
        $this->assertSame(2, $summary['orders']);
    }

    public function test_closing_a_shift_records_the_difference(): void
    {
        $admin = $this->admin();
        $service = app(CashierShiftService::class);
        $shift = $service->open($admin, 100_000);

        $closed = $service->close($shift, 90_000, 'یک اسکناس کم');

        $this->assertSame(100_000, $closed->expected_cash);
        $this->assertSame(-10_000, $closed->difference);
        $this->assertFalse($closed->isOpen());
    }

    public function test_a_cash_refund_during_the_shift_comes_out_of_the_expected_cash(): void
    {
        $admin = $this->admin();
        $service = app(CashierShiftService::class);
        $shift = $service->open($admin, 0);
        $variant = $this->variant(100_000);

        Livewire::actingAs($admin)->test(Cashier::class)->call('quickAdd', $variant->id)->set('paymentMethod', 'cash')->call('checkout');
        $ticket = Order::sole()->items->first()->tickets->first();
        app(RefundService::class)->refundTicket($ticket);

        $this->assertSame(0, $service->summary($shift->fresh())['expected_cash']);
    }

    public function test_only_one_shift_can_be_open_and_a_closed_one_cannot_be_closed_again(): void
    {
        $admin = $this->admin();
        $service = app(CashierShiftService::class);
        $shift = $service->open($admin, 0);

        try {
            $service->open($admin, 0);
            $this->fail('Expected a second open shift to be refused.');
        } catch (TicketingException) {
        }

        $service->close($shift, 0);

        $this->expectException(TicketingException::class);

        $service->close($shift->fresh(), 0);
    }

    public function test_sales_without_an_open_shift_still_work(): void
    {
        $admin = $this->admin();
        $variant = $this->variant();

        Livewire::actingAs($admin)->test(Cashier::class)->call('quickAdd', $variant->id)->call('checkout');

        $this->assertNull(Order::sole()->cashier_shift_id);
    }

    public function test_the_cashier_page_can_open_and_close_the_shift(): void
    {
        $admin = $this->admin();

        $page = Livewire::actingAs($admin)->test(Cashier::class)->set('shiftFloat', '250000')->call('openShift');
        $this->assertSame(250_000, CashierShift::sole()->opening_float);

        $page->set('countedCash', '250000')->call('closeShift');
        $this->assertSame(0, CashierShift::sole()->difference);
    }

    public function test_a_split_payment_records_a_cash_and_a_card_part(): void
    {
        $admin = $this->admin();
        $variant = $this->variant(100_000);

        Livewire::actingAs($admin)->test(Cashier::class)
            ->call('quickAdd', $variant->id)
            ->set('paymentMethod', 'split')->set('splitCash', '30000')
            ->call('checkout');

        $payments = Payment::orderBy('id')->get();
        $this->assertSame([PaymentMethod::Cash, PaymentMethod::Card], $payments->pluck('method')->all());
        $this->assertSame([30_000, 70_000], $payments->map(fn ($p) => (int) $p->amount)->all());
        $this->assertSame(100_000, (int) Order::sole()->amount_paid);
    }

    public function test_a_split_with_no_sensible_cash_part_is_refused_and_sells_nothing(): void
    {
        $admin = $this->admin();
        $variant = $this->variant(100_000);

        Livewire::actingAs($admin)->test(Cashier::class)
            ->call('quickAdd', $variant->id)
            ->set('paymentMethod', 'split')->set('splitCash', '100000')
            ->call('checkout');

        $this->assertSame(0, Order::count());
    }

    public function test_cash_received_is_stored_with_the_change_and_too_little_is_refused(): void
    {
        $admin = $this->admin();
        $variant = $this->variant(60_000);
        $page = Livewire::actingAs($admin)->test(Cashier::class)->call('quickAdd', $variant->id)->set('paymentMethod', 'cash');

        $page->set('cashReceived', '50000')->call('checkout');
        $this->assertSame(0, Order::count());

        $page->set('cashReceived', '100000');
        $this->assertSame(40_000, $page->instance()->changeDue());
        $page->call('checkout');

        $this->assertSame(40_000, Payment::sole()->metadata['change']);
    }

    public function test_quick_add_and_the_barcode_field_merge_into_one_cart_line(): void
    {
        $admin = $this->admin();
        $variant = $this->variant(10_000, ['code' => 'ADULT-1']);

        $page = Livewire::actingAs($admin)->test(Cashier::class)
            ->call('quickAdd', $variant->id)
            ->set('barcode', 'ADULT-1')->call('addByCode');

        $this->assertCount(1, $page->get('cart'));
        $this->assertSame(2, $page->get('cart')[0]['quantity']);
        $this->assertSame('', $page->get('barcode'));
    }

    public function test_an_unknown_barcode_adds_nothing(): void
    {
        $page = Livewire::actingAs($this->admin())->test(Cashier::class)->set('barcode', 'NOPE')->call('addByCode');

        $this->assertCount(0, $page->get('cart'));
    }

    public function test_the_best_selling_types_are_offered_as_quick_buttons(): void
    {
        $admin = $this->admin();
        $popular = $this->variant();
        $rare = $this->variant();
        $unsold = $this->variant();
        $issuer = app(TicketIssuer::class);
        $issuer->issue(['source_type' => TicketSourceType::Cashier, 'items' => [['ticket_variant_id' => $popular->id, 'quantity' => 5]]]);
        $issuer->issue(['source_type' => TicketSourceType::Cashier, 'items' => [['ticket_variant_id' => $rare->id, 'quantity' => 1]]]);

        $ids = Livewire::actingAs($admin)->test(Cashier::class)->instance()->getQuickVariants()->pluck('id')->all();

        $this->assertSame([$popular->id, $rare->id], $ids);
        $this->assertNotContains($unsold->id, $ids);
    }

    public function test_printing_a_whole_order_needs_cashier_access_and_lists_every_ticket(): void
    {
        $variant = $this->variant();
        $order = app(TicketIssuer::class)->issue(['source_type' => TicketSourceType::Cashier, 'items' => [['ticket_variant_id' => $variant->id, 'quantity' => 3]]]);

        $stranger = User::factory()->create();
        $stranger->assignRole(Role::firstOrCreate(['name' => 'accountant']));
        $this->actingAs($stranger)->get(route('admission-orders.print', $order))->assertForbidden();

        $cashier = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'box-office']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'cashier.access', 'guard_name' => 'web']));
        $cashier->assignRole($role);

        $response = $this->actingAs($cashier)->get(route('admission-orders.print', [$order, 'auto' => 1, 'paper' => 'thermal']));

        $response->assertSuccessful()->assertSee('size: 80mm auto', false);
        $this->assertSame(3, substr_count($response->getContent(), '<iframe'));
    }
}
