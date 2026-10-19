<?php

namespace Tests\Feature\Hospitality;

use App\Enums\BookingPaymentKind;
use App\Enums\BookingPaymentMethod;
use App\Enums\BookingPaymentStatus;
use App\Enums\BookingStatus;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Models\Booking;
use App\Models\BookingCharge;
use App\Models\BookingPayment;
use App\Models\ExternalApiClient;
use App\Models\Guest;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingPaymentsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    /**
     * The stay charge the booking creates for itself is replaced so the folio
     * total is exactly $total.
     */
    private function booking(int $total = 1_000_000, BookingStatus $status = BookingStatus::Confirmed, ?Guest $guest = null): Booking
    {
        $booking = Booking::factory()->create(['status' => $status, 'guest_id' => ($guest ?? Guest::factory()->create())->id]);
        $booking->charges()->delete();
        BookingCharge::query()->create(['booking_id' => $booking->id, 'title' => 'اقامت', 'quantity' => 1, 'unit_amount' => $total]);

        return $booking->fresh();
    }

    public function test_a_payment_lowers_the_balance_and_moves_the_payment_status(): void
    {
        $booking = $this->booking();
        $this->assertSame(BookingPaymentStatus::Unpaid, $booking->paymentStatus());
        $this->assertSame(1_000_000, $booking->balanceDue());

        $booking->recordPayment(400_000, BookingPaymentMethod::Cash, userId: $this->admin()->id);

        $booking = $booking->fresh();
        $this->assertSame(400_000, $booking->paidAmount());
        $this->assertSame(600_000, $booking->balanceDue());
        $this->assertSame(BookingPaymentStatus::Partial, $booking->paymentStatus());

        $booking->recordPayment(600_000, BookingPaymentMethod::Card, reference: 'RRN-1');

        $booking = $booking->fresh();
        $this->assertSame(0, $booking->balanceDue());
        $this->assertSame(BookingPaymentStatus::Paid, $booking->paymentStatus());
        $this->assertSame('RRN-1', $booking->payments()->latest('id')->first()->reference);
    }

    public function test_an_advance_counts_like_a_payment(): void
    {
        $booking = $this->booking();

        $booking->recordPayment(300_000, BookingPaymentMethod::Transfer, BookingPaymentKind::Deposit);

        $this->assertSame(700_000, $booking->fresh()->balanceDue());
        $this->assertSame(BookingPaymentKind::Deposit, $booking->payments()->sole()->kind);
    }

    public function test_a_payment_above_the_balance_or_without_an_amount_is_refused_and_leaves_no_trace(): void
    {
        $booking = $this->booking(500_000);
        $booking->recordPayment(200_000, BookingPaymentMethod::Cash);

        foreach ([300_001, 0, -5] as $amount) {
            try {
                $booking->recordPayment($amount, BookingPaymentMethod::Cash);
                $this->fail("{$amount} should have been refused");
            } catch (InvalidArgumentException) {
                // expected
            }
        }

        $this->assertSame(1, BookingPayment::count());
        $this->assertSame(300_000, $booking->fresh()->balanceDue());
    }

    public function test_refunds_come_out_of_received_money_only(): void
    {
        $guest = Guest::factory()->create(['wallet_balance' => 400_000]);
        $booking = $this->booking(1_000_000, guest: $guest);
        $booking->payFromWallet(400_000);
        $booking->fresh()->recordPayment(200_000, BookingPaymentMethod::Cash);
        $booking = $booking->fresh();

        $this->assertSame(600_000, $booking->paidAmount());

        try {
            $booking->recordPayment(200_001, BookingPaymentMethod::Cash, BookingPaymentKind::Refund);
            $this->fail('a refund above the received money should be refused');
        } catch (InvalidArgumentException) {
            // wallet money is not refundable this way
        }

        $booking->recordPayment(50_000, BookingPaymentMethod::Cash, BookingPaymentKind::Refund, note: 'تخفیف نظافت');

        $booking = $booking->fresh();
        $this->assertSame(150_000, $booking->receivedAmount());
        $this->assertSame(550_000, $booking->paidAmount());
        $this->assertSame(450_000, $booking->balanceDue());
    }

    public function test_a_cancelled_booking_takes_refunds_but_no_new_payments(): void
    {
        $booking = $this->booking();
        $booking->recordPayment(300_000, BookingPaymentMethod::Card);
        $booking->update(['status' => BookingStatus::Cancelled]);
        $booking = $booking->fresh();

        try {
            $booking->recordPayment(1, BookingPaymentMethod::Cash);
            $this->fail('a cancelled booking must not take payments');
        } catch (InvalidArgumentException) {
            // expected
        }

        $booking->recordPayment(300_000, BookingPaymentMethod::Card, BookingPaymentKind::Refund);

        $this->assertSame(0, $booking->fresh()->receivedAmount());
    }

    public function test_wallet_and_desk_payments_share_one_balance(): void
    {
        $guest = Guest::factory()->create(['wallet_balance' => 5_000_000]);
        $booking = $this->booking(1_000_000, guest: $guest);

        $booking->recordPayment(700_000, BookingPaymentMethod::Cash);
        $booking = $booking->fresh();

        $this->assertSame(300_000, $booking->walletPayableAmount());

        try {
            $booking->payFromWallet(300_001);
            $this->fail('the wallet must not pay more than the balance left after desk payments');
        } catch (InvalidArgumentException) {
            // expected
        }

        $booking->payFromWallet(300_000);

        $booking = $booking->fresh();
        $this->assertSame(0, $booking->balanceDue());
        $this->assertSame(BookingPaymentStatus::Paid, $booking->paymentStatus());
    }

    public function test_the_table_action_records_a_payment_and_a_bad_amount_records_nothing(): void
    {
        $booking = $this->booking();
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(ListBookings::class)
            ->callTableAction('recordPayment', $booking, data: [
                'kind' => BookingPaymentKind::Payment->value,
                'method' => BookingPaymentMethod::Cash->value,
                'amount' => 250_000,
                'reference' => 'رسید ۱۲',
            ])
            ->assertHasNoTableActionErrors();

        $payment = $booking->payments()->sole();
        $this->assertSame(250_000, $payment->amount);
        $this->assertSame($admin->id, $payment->created_by);
        $this->assertSame('رسید ۱۲', $payment->reference);

        Livewire::actingAs($admin)
            ->test(ListBookings::class)
            ->callTableAction('recordPayment', $booking, data: [
                'kind' => BookingPaymentKind::Payment->value,
                'method' => BookingPaymentMethod::Cash->value,
                'amount' => 9_999_999,
            ]);

        $this->assertSame(1, $booking->payments()->count());
    }

    public function test_the_balance_filter_lists_only_bookings_with_money_due(): void
    {
        $owing = $this->booking(1_000_000);
        $settled = $this->booking(1_000_000);
        $settled->recordPayment(1_000_000, BookingPaymentMethod::Cash);
        $cancelled = $this->booking(1_000_000, BookingStatus::Cancelled);

        Livewire::actingAs($this->admin())
            ->test(ListBookings::class)
            ->filterTable('has_balance', true)
            ->assertCanSeeTableRecords([$owing])
            ->assertCanNotSeeTableRecords([$settled, $cancelled]);
    }

    public function test_the_external_api_reports_the_payment_state(): void
    {
        $room = Room::factory()->create();
        $client = ExternalApiClient::factory()->create();
        $headers = ['X-API-Key' => $client->api_key, 'X-API-Secret' => 'test-secret'];

        $create = $this->postJson('/api/v1/external/bookings', [
            'room_id' => $room->id,
            'check_in_date' => '2026-12-01',
            'check_out_date' => '2026-12-03',
            'guest' => ['name' => 'علی', 'mobile' => '09120001122'],
        ], [...$headers, 'Idempotency-Key' => 'pay-1'])->assertStatus(201);

        $booking = Booking::where('reference_code', $create->json('reference_code'))->sole();
        $total = $booking->totalAmount();
        $booking->recordPayment(1, BookingPaymentMethod::Cash);

        $this->getJson("/api/v1/external/bookings/{$booking->reference_code}", $headers)
            ->assertOk()
            ->assertJsonPath('total_amount', $total)
            ->assertJsonPath('paid_amount', 1)
            ->assertJsonPath('balance_due', $total - 1)
            ->assertJsonPath('payment_status', 'partial');
    }
}
