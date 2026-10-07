<?php

namespace Tests\Feature;

use App\Enums\AdmissionTicketStatus;
use App\Models\AdmissionTicket;
use App\Models\Guest;
use App\Models\Product;
use App\Models\TicketVariant;
use App\Models\TicketVariantPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAppTicketTest extends TestCase
{
    use RefreshDatabase;

    private function variantWithPrice(int $amount = 200000): TicketVariant
    {
        $variant = TicketVariant::factory()->create();
        TicketVariantPrice::factory()->create(['ticket_variant_id' => $variant->id, 'amount' => $amount]);

        return $variant;
    }

    public function test_a_guest_cannot_view_tickets_without_logging_in(): void
    {
        $this->get(route('customer-app.tickets'))
            ->assertRedirect(route('customer-app.auth.login'));
    }

    public function test_the_tickets_page_lists_active_products_with_priced_variants(): void
    {
        $guest = Guest::factory()->create();
        $variant = $this->variantWithPrice(150000);

        $response = $this->actingAs($guest, 'customer')->get(route('customer-app.tickets'));

        $response->assertOk();
        $response->assertSee($variant->product->name);
        $response->assertSee($variant->name);
        $response->assertSee(number_format(150000));
    }

    public function test_a_guest_with_enough_balance_can_buy_a_ticket_paid_from_their_wallet(): void
    {
        $guest = Guest::factory()->create(['mobile' => '09120000031', 'wallet_balance' => 500000]);
        $variant = $this->variantWithPrice(200000);

        $response = $this->actingAs($guest, 'customer')
            ->post(route('customer-app.tickets.store'), ['ticket_variant_id' => $variant->id, 'quantity' => 1]);

        $response->assertRedirect(route('customer-app.tickets'));

        $guest->refresh();
        $this->assertSame(300000, (int) $guest->wallet_balance);

        $ticket = AdmissionTicket::where('customer_phone', '09120000031')->first();
        $this->assertNotNull($ticket);
        $this->assertSame(AdmissionTicketStatus::Active, $ticket->status);
    }

    public function test_a_guest_without_enough_balance_cannot_buy_a_ticket(): void
    {
        $guest = Guest::factory()->create(['mobile' => '09120000032', 'wallet_balance' => 50000]);
        $variant = $this->variantWithPrice(200000);

        $response = $this->actingAs($guest, 'customer')
            ->post(route('customer-app.tickets.store'), ['ticket_variant_id' => $variant->id, 'quantity' => 1]);

        $response->assertSessionHasErrors('ticket_variant_id');

        $guest->refresh();
        $this->assertSame(50000, (int) $guest->wallet_balance);
        $this->assertSame(0, AdmissionTicket::where('customer_phone', '09120000032')->count());
    }

    public function test_a_guest_only_sees_tickets_bought_with_their_own_phone_number(): void
    {
        $guest = Guest::factory()->create(['mobile' => '09120000033']);
        $product = Product::factory()->create();
        $variant = TicketVariant::factory()->create(['product_id' => $product->id]);

        $myTicket = AdmissionTicket::factory()->create([
            'ticket_variant_id' => $variant->id,
            'customer_phone' => '09120000033',
        ]);
        $otherTicket = AdmissionTicket::factory()->create([
            'ticket_variant_id' => $variant->id,
            'customer_phone' => '09120000099',
        ]);

        $response = $this->actingAs($guest, 'customer')->get(route('customer-app.tickets'));

        $response->assertSee($myTicket->ticket_number);
        $response->assertDontSee($otherTicket->ticket_number);
    }
}
