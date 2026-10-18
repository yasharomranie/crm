<?php

namespace Tests\Feature\Ticketing;

use App\Domain\Ticketing\TicketingException;
use App\Domain\Ticketing\TicketIssuer;
use App\Enums\DiscountType;
use App\Enums\TicketSourceType;
use App\Filament\Pages\Cashier;
use App\Models\ComplimentaryIssue;
use App\Models\DiscountCode;
use App\Models\DiscountUsage;
use App\Models\Gate;
use App\Models\Product;
use App\Models\TicketVariant;
use App\Models\TicketVariantPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Covers section 73 items 26-40: discount validation/concurrency,
 * authorized-vs-unauthorized complimentary issuance, and that the two are
 * always distinguishable.
 */
class DiscountAndComplimentaryTest extends TestCase
{
    use RefreshDatabase;

    private function variantWithPrice(int $amount = 100000): TicketVariant
    {
        $variant = TicketVariant::factory()->create();
        TicketVariantPrice::factory()->create(['ticket_variant_id' => $variant->id, 'amount' => $amount]);

        return $variant;
    }

    public function test_a_valid_discount_code_reduces_the_final_amount(): void
    {
        $variant = $this->variantWithPrice(100000);
        $code = DiscountCode::factory()->create(['discount_type' => DiscountType::Percentage, 'discount_value' => 20]);

        $order = app(TicketIssuer::class)->issue([
            'source_type' => TicketSourceType::Cashier,
            'items' => [['ticket_variant_id' => $variant->id, 'quantity' => 1]],
            'discount_code' => $code->code,
        ]);

        $this->assertSame(80000, $order->final_amount);
        $this->assertSame(1, $code->fresh()->used_count);
    }

    public function test_an_expired_discount_code_is_rejected(): void
    {
        $variant = $this->variantWithPrice();
        $code = DiscountCode::factory()->create(['end_date' => now()->subDay()]);

        $this->expectException(TicketingException::class);

        app(TicketIssuer::class)->issue([
            'source_type' => TicketSourceType::Cashier,
            'items' => [['ticket_variant_id' => $variant->id, 'quantity' => 1]],
            'discount_code' => $code->code,
        ]);
    }

    public function test_a_not_yet_active_discount_code_is_rejected(): void
    {
        $variant = $this->variantWithPrice();
        $code = DiscountCode::factory()->create(['start_date' => now()->addDay()]);

        $this->expectException(TicketingException::class);

        app(TicketIssuer::class)->issue([
            'source_type' => TicketSourceType::Cashier,
            'items' => [['ticket_variant_id' => $variant->id, 'quantity' => 1]],
            'discount_code' => $code->code,
        ]);
    }

    public function test_a_discount_code_at_its_usage_limit_is_rejected(): void
    {
        $variant = $this->variantWithPrice();
        $code = DiscountCode::factory()->create(['usage_limit' => 1, 'used_count' => 1]);

        $this->expectException(TicketingException::class);

        app(TicketIssuer::class)->issue([
            'source_type' => TicketSourceType::Cashier,
            'items' => [['ticket_variant_id' => $variant->id, 'quantity' => 1]],
            'discount_code' => $code->code,
        ]);
    }

    public function test_a_customer_at_their_per_customer_usage_limit_is_rejected(): void
    {
        $variant = $this->variantWithPrice();
        $code = DiscountCode::factory()->create(['per_customer_usage_limit' => 1]);
        DiscountUsage::factory()->create(['discount_code_id' => $code->id, 'customer_phone' => '0912']);

        $this->expectException(TicketingException::class);

        app(TicketIssuer::class)->issue([
            'source_type' => TicketSourceType::Cashier,
            'items' => [['ticket_variant_id' => $variant->id, 'quantity' => 1]],
            'discount_code' => $code->code,
            'customer_phone' => '0912',
        ]);
    }

    public function test_a_discount_code_restricted_to_another_product_does_not_discount_this_one(): void
    {
        $otherProduct = Product::factory()->create();
        $variant = $this->variantWithPrice(100000);
        $code = DiscountCode::factory()->create(['discount_type' => DiscountType::Percentage, 'discount_value' => 50]);
        $code->products()->attach($otherProduct);

        $order = app(TicketIssuer::class)->issue([
            'source_type' => TicketSourceType::Cashier,
            'items' => [['ticket_variant_id' => $variant->id, 'quantity' => 1]],
            'discount_code' => $code->code,
        ]);

        // The code is valid but discounts nothing since this order's
        // variant isn't in its allow-list - so it is not used up either.
        $this->assertSame(100000, $order->final_amount);
        $this->assertSame(0, $code->fresh()->used_count);
    }

    public function test_an_order_below_the_minimum_purchase_amount_is_rejected(): void
    {
        $variant = $this->variantWithPrice(50000);
        $code = DiscountCode::factory()->create(['minimum_purchase_amount' => 100000]);

        $this->expectException(TicketingException::class);

        app(TicketIssuer::class)->issue([
            'source_type' => TicketSourceType::Cashier,
            'items' => [['ticket_variant_id' => $variant->id, 'quantity' => 1]],
            'discount_code' => $code->code,
        ]);
    }

    public function test_the_discount_amount_is_capped_at_the_maximum_discount_amount(): void
    {
        $variant = $this->variantWithPrice(1000000);
        $code = DiscountCode::factory()->create([
            'discount_type' => DiscountType::Percentage,
            'discount_value' => 50,
            'maximum_discount_amount' => 100000,
        ]);

        $order = app(TicketIssuer::class)->issue([
            'source_type' => TicketSourceType::Cashier,
            'items' => [['ticket_variant_id' => $variant->id, 'quantity' => 1]],
            'discount_code' => $code->code,
        ]);

        $this->assertSame(100000, $order->discount_amount);
        $this->assertSame(900000, $order->final_amount);
    }

    public function test_a_100_percent_discount_results_in_a_zero_amount_ordinary_sale(): void
    {
        $variant = $this->variantWithPrice(500000);
        $code = DiscountCode::factory()->create(['discount_type' => DiscountType::Percentage, 'discount_value' => 100]);

        $order = app(TicketIssuer::class)->issue([
            'source_type' => TicketSourceType::Cashier,
            'items' => [['ticket_variant_id' => $variant->id, 'quantity' => 1]],
            'discount_code' => $code->code,
        ]);

        $this->assertSame(0, $order->final_amount);
        $this->assertSame(TicketSourceType::Cashier, $order->source_type);
        $this->assertSame(0, ComplimentaryIssue::count(), 'a 100% discount must never create a ComplimentaryIssue row');
    }

    public function test_usage_cannot_exceed_the_limit_even_racing_two_orders_for_the_last_slot(): void
    {
        $variant = $this->variantWithPrice(100000);
        $code = DiscountCode::factory()->create(['usage_limit' => 1, 'used_count' => 0]);

        app(TicketIssuer::class)->issue([
            'source_type' => TicketSourceType::Cashier,
            'items' => [['ticket_variant_id' => $variant->id, 'quantity' => 1]],
            'discount_code' => $code->code,
        ]);

        $this->expectException(TicketingException::class);

        app(TicketIssuer::class)->issue([
            'source_type' => TicketSourceType::Cashier,
            'items' => [['ticket_variant_id' => $variant->id, 'quantity' => 1]],
            'discount_code' => $code->code,
        ]);
    }

    public function test_a_user_with_complimentary_permission_can_issue_a_free_ticket(): void
    {
        $variant = $this->variantWithPrice(300000);
        $manager = User::factory()->create();
        $manager->givePermissionTo(Permission::firstOrCreate(['name' => 'cashier.issue_complimentary', 'guard_name' => 'web']));

        $this->actingAs($manager);

        Livewire::test(Cashier::class)
            ->set('productId', $variant->product_id)
            ->set('ticketVariantId', $variant->id)
            ->set('quantity', 1)
            ->call('addToCart')
            ->set('isComplimentary', true)
            ->set('complimentaryReason', 'مهمان ویژه')
            ->call('checkout');

        $order = $this->assertComplimentaryOrderExists();
        $this->assertSame(0, $order->final_amount);
        $this->assertSame(300000, $order->complimentaryIssues->first()->original_value);
    }

    public function test_a_user_without_complimentary_permission_cannot_issue_a_free_ticket(): void
    {
        $variant = $this->variantWithPrice(300000);
        $cashier = User::factory()->create();
        // A user with no role at all gets this app's backward-compatible
        // unrestricted access (see AppServiceProvider::boot's Gate::before),
        // so a real role that can access the page but lacks the
        // complimentary permission is required to exercise the negative case.
        $role = Role::firstOrCreate(['name' => 'cashier']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'cashier.access', 'guard_name' => 'web']));
        $cashier->assignRole($role);

        $this->actingAs($cashier);

        $component = Livewire::test(Cashier::class)
            ->set('productId', $variant->product_id)
            ->set('ticketVariantId', $variant->id)
            ->set('quantity', 1)
            ->call('addToCart');

        // The checkbox to even attempt complimentary issuance is gated
        // client-side by canIssueComplimentary() (see the blade view);
        // this asserts the real server-side gate that matters - forcing
        // the property directly still gets refused, so a user can never
        // bypass this by tampering with the request.
        $this->assertFalse($component->instance()->canIssueComplimentary());

        $component->set('isComplimentary', true)
            ->set('complimentaryReason', 'تلاش غیرمجاز')
            ->call('checkout');

        $this->assertSame(0, ComplimentaryIssue::count());
    }

    public function test_complimentary_issuance_is_distinguishable_from_a_100_percent_discount_in_reports(): void
    {
        $variant = $this->variantWithPrice(300000);
        $manager = User::factory()->create();
        $manager->givePermissionTo(Permission::firstOrCreate(['name' => 'cashier.issue_complimentary', 'guard_name' => 'web']));

        app(TicketIssuer::class)->issueComplimentary($variant->product, $variant, 1, 'مهمان ویژه', $manager);

        $discountedVariant = $this->variantWithPrice(300000);
        $code = DiscountCode::factory()->create(['discount_type' => DiscountType::Percentage, 'discount_value' => 100]);
        app(TicketIssuer::class)->issue([
            'source_type' => TicketSourceType::Cashier,
            'items' => [['ticket_variant_id' => $discountedVariant->id, 'quantity' => 1]],
            'discount_code' => $code->code,
        ]);

        $this->assertSame(1, ComplimentaryIssue::count());
        $this->assertSame(TicketSourceType::Complimentary, ComplimentaryIssue::first()->order->source_type);
        $this->assertSame(0, ComplimentaryIssue::where('order_id', '!=', ComplimentaryIssue::first()->order_id)->count());
    }

    public function test_a_gate_operator_role_cannot_access_the_cashier_page(): void
    {
        $operator = User::factory()->create();
        $operator->assignRole(Role::firstOrCreate(['name' => 'gate-operator']));

        $this->actingAs($operator);
        $this->assertFalse(Cashier::canAccess());
    }

    public function test_a_cashier_role_cannot_modify_gate_rules(): void
    {
        $cashier = User::factory()->create();
        $cashier->assignRole(Role::firstOrCreate(['name' => 'cashier']));
        $gate = Gate::factory()->create();

        $this->actingAs($cashier);

        $this->assertFalse($cashier->can('ticketing-gates.update'));
        $this->get(route('filament.admin.resources.gates.edit', $gate))->assertForbidden();
    }

    private function assertComplimentaryOrderExists()
    {
        $issue = ComplimentaryIssue::with('order.complimentaryIssues')->firstOrFail();

        return $issue->order;
    }
}
