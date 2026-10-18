<?php

namespace Tests\Feature\Crm;

use App\Domain\Ticketing\RefundService;
use App\Domain\Ticketing\TicketIssuer;
use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\TicketSourceType;
use App\Exceptions\InsufficientWalletBalanceException;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Models\AdmissionTicket;
use App\Models\Booking;
use App\Models\BookingCharge;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\TicketVariant;
use App\Models\TicketVariantPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WalletLoopTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    /**
     * The booking's own "اقامت" charge is created from the room-type
     * price; this replaces it so the folio total is exactly $total.
     */
    private function bookingWithFolio(Guest $guest, int $total, BookingStatus $status = BookingStatus::Confirmed): Booking
    {
        $booking = Booking::factory()->create(['guest_id' => $guest->id, 'status' => $status]);
        $booking->charges()->delete();
        BookingCharge::query()->create(['booking_id' => $booking->id, 'title' => 'اقامت', 'quantity' => 1, 'unit_amount' => $total]);

        return $booking->fresh();
    }

    public function test_paying_part_of_the_folio_from_the_wallet_moves_both_sides(): void
    {
        $guest = Guest::factory()->create(['wallet_balance' => 300_000]);
        $booking = $this->bookingWithFolio($guest, 1_000_000);

        $booking->payFromWallet(250_000);

        $this->assertSame(50_000, (int) $guest->fresh()->wallet_balance);
        $this->assertSame(250_000, (int) $booking->fresh()->wallet_paid_amount);
        $this->assertSame(750_000, $booking->fresh()->walletPayableAmount());
        $this->assertSame($booking->id, $guest->walletTransactions()->sole()->booking_id);
    }

    public function test_the_wallet_cannot_pay_more_than_the_folio_or_the_balance(): void
    {
        $guest = Guest::factory()->create(['wallet_balance' => 5_000_000]);
        $booking = $this->bookingWithFolio($guest, 1_000_000);

        try {
            $booking->payFromWallet(1_000_001);
            $this->fail('Expected the overpayment to be refused.');
        } catch (InvalidArgumentException) {
            $this->assertSame(5_000_000, (int) $guest->fresh()->wallet_balance);
        }

        $poorGuest = Guest::factory()->create(['wallet_balance' => 1_000]);
        $poorBooking = $this->bookingWithFolio($poorGuest, 1_000_000);

        $this->expectException(InsufficientWalletBalanceException::class);

        $poorBooking->payFromWallet(2_000);
    }

    public function test_a_failed_wallet_debit_does_not_mark_the_folio_as_paid(): void
    {
        $guest = Guest::factory()->create(['wallet_balance' => 1_000]);
        $booking = $this->bookingWithFolio($guest, 1_000_000);

        try {
            $booking->payFromWallet(2_000);
        } catch (InsufficientWalletBalanceException) {
        }

        $this->assertSame(0, (int) $booking->fresh()->wallet_paid_amount);
    }

    public function test_a_cancelled_or_checked_out_booking_cannot_be_paid_from_the_wallet(): void
    {
        $guest = Guest::factory()->create(['wallet_balance' => 100_000]);
        $booking = $this->bookingWithFolio($guest, 500_000, BookingStatus::Cancelled);

        $this->expectException(InvalidArgumentException::class);

        $booking->payFromWallet(10_000);
    }

    public function test_cashback_is_only_earned_on_the_part_not_paid_from_the_wallet(): void
    {
        $guest = Guest::factory()->create(['wallet_balance' => 400_000]);
        $booking = $this->bookingWithFolio($guest, 1_000_000, BookingStatus::CheckedIn);
        $booking->payFromWallet(400_000);

        $booking->fresh()->checkOut();

        // 2% of the 600,000 paid another way, not of the whole 1,000,000.
        $this->assertSame(12_000, (int) $guest->fresh()->wallet_balance);
    }

    public function test_the_pay_from_wallet_action_is_offered_and_works_in_the_bookings_list(): void
    {
        $guest = Guest::factory()->create(['wallet_balance' => 200_000]);
        $booking = $this->bookingWithFolio($guest, 1_000_000);

        Livewire::actingAs($this->admin())
            ->test(ListBookings::class)
            ->assertTableActionVisible('payFromWallet', $booking)
            ->callTableAction('payFromWallet', $booking, ['amount' => 150_000]);

        $this->assertSame(50_000, (int) $guest->fresh()->wallet_balance);
    }

    public function test_the_action_is_hidden_when_the_guest_has_no_balance(): void
    {
        $guest = Guest::factory()->create(['wallet_balance' => 0]);
        $booking = $this->bookingWithFolio($guest, 1_000_000);

        Livewire::actingAs($this->admin())
            ->test(ListBookings::class)
            ->assertTableActionHidden('payFromWallet', $booking);
    }

    public function test_refunding_a_wallet_paid_ticket_returns_the_money_to_the_wallet(): void
    {
        $guest = Guest::factory()->create(['mobile' => '09120000077', 'wallet_balance' => 500_000]);
        $variant = TicketVariant::factory()->create();
        TicketVariantPrice::factory()->create(['ticket_variant_id' => $variant->id, 'amount' => 200_000]);

        $this->actingAs($guest, 'customer')
            ->post(route('customer-app.tickets.store'), ['ticket_variant_id' => $variant->id, 'quantity' => 1]);

        $this->assertSame(300_000, (int) $guest->fresh()->wallet_balance);
        $payment = Payment::sole();
        $this->assertSame(PaymentMethod::Wallet, $payment->method);

        $ticket = AdmissionTicket::firstOrFail();
        app(RefundService::class)->refundTicket($ticket, 'انصراف');

        $this->assertSame(500_000, (int) $guest->fresh()->wallet_balance);
    }

    public function test_refunding_a_ticket_paid_another_way_does_not_touch_any_wallet(): void
    {
        $guest = Guest::factory()->create(['wallet_balance' => 10_000]);
        $variant = TicketVariant::factory()->create();
        TicketVariantPrice::factory()->create(['ticket_variant_id' => $variant->id, 'amount' => 100_000]);
        $order = app(TicketIssuer::class)->issue([
            'source_type' => TicketSourceType::Cashier,
            'items' => [['ticket_variant_id' => $variant->id, 'quantity' => 1]],
        ]);
        $order->payments()->create(['method' => PaymentMethod::Cash, 'amount' => 100_000, 'status' => 'paid', 'paid_at' => now()]);

        app(RefundService::class)->refundTicket(AdmissionTicket::firstOrFail());

        $this->assertSame(10_000, (int) $guest->fresh()->wallet_balance);
    }
}
