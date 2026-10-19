<?php

namespace Tests\Feature\Hospitality;

use App\Domain\Payments\BookingPaymentLinkService;
use App\Domain\Payments\GatewayException;
use App\Enums\BookingPaymentKind;
use App\Enums\BookingPaymentMethod;
use App\Enums\BookingStatus;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Models\Booking;
use App\Models\BookingCharge;
use App\Models\BookingPaymentLink;
use App\Models\PaymentGatewaySetting;
use App\Models\SmsSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingOnlinePaymentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    private function enableGateway(): void
    {
        PaymentGatewaySetting::current()->update(['is_enabled' => true, 'merchant_id' => 'test-merchant']);
    }

    private function booking(BookingStatus $status = BookingStatus::Confirmed, int $total = 1_000_000): Booking
    {
        $booking = Booking::factory()->create(['status' => $status]);
        $booking->charges()->delete();
        BookingCharge::query()->create(['booking_id' => $booking->id, 'title' => 'اقامت', 'quantity' => 1, 'unit_amount' => $total]);

        return $booking->fresh();
    }

    private function service(): BookingPaymentLinkService
    {
        return app(BookingPaymentLinkService::class);
    }

    public function test_a_link_is_made_for_the_balance_and_reused_for_the_same_amount(): void
    {
        $this->enableGateway();
        $booking = $this->booking();

        $link = $this->service()->linkFor($booking);

        $this->assertSame(1_000_000, $link->amount);
        $this->assertSame($link->id, $this->service()->linkFor($booking)->id);
        $this->assertNotSame($link->id, $this->service()->linkFor($booking, 400_000)->id);
        $this->assertSame(route('booking-pay.show', $link->token), $link->url());
    }

    public function test_a_link_needs_the_gateway_a_live_booking_and_a_sensible_amount(): void
    {
        $booking = $this->booking();

        try {
            $this->service()->linkFor($booking);
            $this->fail('The gateway is off.');
        } catch (GatewayException $exception) {
            $this->assertStringContainsString('فعال نیست', $exception->getMessage());
        }

        $this->enableGateway();

        foreach ([0, 1_000_001] as $amount) {
            try {
                $this->service()->linkFor($booking, $amount);
                $this->fail("{$amount} should have been refused.");
            } catch (GatewayException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->expectException(GatewayException::class);
        $this->service()->linkFor($this->booking(BookingStatus::CheckedOut));
    }

    public function test_the_public_page_shows_the_booking_and_starts_the_payment(): void
    {
        $this->enableGateway();
        Http::fake(['*' => Http::response(['data' => ['code' => 100, 'authority' => 'A0000000000000000000000000000000ZZ']])]);
        $booking = $this->booking();
        $link = $this->service()->linkFor($booking, 300_000);

        $this->get(route('booking-pay.show', $link->token))
            ->assertOk()
            ->assertSee($booking->reference_code)
            ->assertSee($booking->guest->name);

        $this->post(route('booking-pay.start', $link->token))
            ->assertRedirect('https://www.zarinpal.com/pg/StartPay/A0000000000000000000000000000000ZZ');

        $sent = Http::recorded()->map(fn (array $pair) => $pair[0]->data())->first();
        $this->assertSame(3_000_000, $sent['amount'] ?? null);
        $this->assertSame('A0000000000000000000000000000000ZZ', $link->fresh()->authority);
    }

    public function test_an_unknown_token_is_a_404(): void
    {
        $this->get(route('booking-pay.show', 'nope'))->assertNotFound();
    }

    public function test_a_verified_payment_lands_in_the_ledger_once_and_confirms_a_held_booking(): void
    {
        $this->enableGateway();
        $booking = $this->booking(BookingStatus::Pending);
        $booking->update(['hold_expires_at' => now()->addHours(12)]);
        $link = $this->service()->linkFor($booking, 400_000);
        $link->update(['authority' => 'AUTH1']);
        Http::fake(['*' => Http::response(['data' => ['code' => 100, 'ref_id' => 777, 'card_pan' => '6037****1234']])]);

        $this->get(route('booking-pay.callback', ['Authority' => 'AUTH1', 'Status' => 'OK']))
            ->assertRedirect(route('booking-pay.show', $link->token));
        // Opening the callback again must not pay twice.
        $this->get(route('booking-pay.callback', ['Authority' => 'AUTH1', 'Status' => 'OK']));

        $booking = $booking->fresh(['payments']);
        $this->assertSame(400_000, $booking->receivedAmount());
        $this->assertSame(1, $booking->payments->count());
        $payment = $booking->payments->first();
        $this->assertSame(BookingPaymentMethod::Online, $payment->method);
        $this->assertSame(BookingPaymentKind::Payment, $payment->kind);
        $this->assertSame('777', $payment->reference);
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertNull($booking->hold_expires_at);
        $this->assertSame(BookingPaymentLink::STATUS_PAID, $link->fresh()->status);

        $this->get(route('booking-pay.show', $link->token))->assertSee('با موفقیت انجام شد')->assertSee('777');
    }

    public function test_a_cancelled_or_refused_payment_records_nothing(): void
    {
        $this->enableGateway();
        $booking = $this->booking();
        $cancelled = $this->service()->linkFor($booking, 100_000);
        $cancelled->update(['authority' => 'AUTHNOK']);

        $this->get(route('booking-pay.callback', ['Authority' => 'AUTHNOK', 'Status' => 'NOK']))
            ->assertRedirect()->assertSessionHasErrors('payment');

        $refused = $this->service()->linkFor($booking, 200_000);
        $refused->update(['authority' => 'AUTHREF']);
        Http::fake(['*' => Http::response(['data' => ['code' => -50]])]);

        $this->get(route('booking-pay.callback', ['Authority' => 'AUTHREF', 'Status' => 'OK']))->assertSessionHasErrors('payment');

        $this->assertSame(0, $booking->fresh(['payments'])->receivedAmount());
        $this->assertSame(BookingPaymentLink::STATUS_PENDING, $cancelled->fresh()->status);
        $this->assertNull($refused->fresh()->authority);
    }

    public function test_money_beyond_the_balance_goes_to_the_guests_wallet(): void
    {
        $this->enableGateway();
        $booking = $this->booking();
        $link = $this->service()->linkFor($booking, 600_000);
        $link->update(['authority' => 'AUTH2']);
        // The desk took cash for most of the balance after the link was made.
        $booking->recordPayment(700_000, BookingPaymentMethod::Cash);
        Http::fake(['*' => Http::response(['data' => ['code' => 100, 'ref_id' => 888]])]);

        $this->get(route('booking-pay.callback', ['Authority' => 'AUTH2', 'Status' => 'OK']));

        $booking = $booking->fresh(['charges', 'payments']);
        $this->assertSame(0, $booking->balanceDue());
        $this->assertSame(1_000_000, $booking->receivedAmount());
        $this->assertSame(300_000, (int) $booking->guest->fresh()->wallet_balance);
    }

    public function test_an_expired_link_cannot_start_a_payment(): void
    {
        $this->enableGateway();
        $link = $this->service()->linkFor($this->booking());
        $link->update(['expires_at' => now()->subMinute()]);

        $this->get(route('booking-pay.show', $link->token))->assertSee('مهلت این لینک تمام شده');

        $this->post(route('booking-pay.start', $link->token))->assertSessionHasErrors('payment');
    }

    public function test_staff_make_and_text_a_link_from_the_bookings_list(): void
    {
        $this->enableGateway();
        Http::fake(['api.sms.ir/*' => Http::response(['status' => 1])]);
        SmsSetting::factory()->create();
        $booking = $this->booking();

        Livewire::actingAs($this->admin())
            ->test(ListBookings::class)
            ->callTableAction('paymentLink', $booking, data: ['amount' => 250_000, 'send_sms' => true])
            ->assertNotified('لینک پرداخت آماده است');

        $link = BookingPaymentLink::sole();
        $this->assertSame(250_000, $link->amount);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.sms.ir') && str_contains(json_encode($request->data()), $link->token));
    }

    public function test_an_expired_hold_with_no_money_cancels_the_booking_without_a_fee(): void
    {
        $expired = $this->booking(BookingStatus::Pending);
        $expired->update(['hold_expires_at' => now()->subMinute()]);
        $paid = $this->booking(BookingStatus::Pending);
        $paid->update(['hold_expires_at' => now()->subMinute()]);
        $paid->recordPayment(100_000, BookingPaymentMethod::Cash, BookingPaymentKind::Deposit);
        $running = $this->booking(BookingStatus::Pending);
        $running->update(['hold_expires_at' => now()->addHour()]);

        $this->assertSame(1, Booking::releaseExpiredHolds());

        $this->assertSame(BookingStatus::Cancelled, $expired->fresh()->status);
        $this->assertSame(0, $expired->fresh()->cancellation_fee);
        $this->assertSame(BookingStatus::Pending, $paid->fresh()->status);
        $this->assertNull($paid->fresh()->hold_expires_at);
        $this->assertSame(BookingStatus::Pending, $running->fresh()->status);
    }

    public function test_confirming_a_booking_by_hand_clears_its_hold(): void
    {
        $booking = $this->booking(BookingStatus::Pending);
        $booking->update(['hold_expires_at' => now()->addHours(5)]);

        $booking->update(['status' => BookingStatus::Confirmed]);

        $this->assertNull($booking->fresh()->hold_expires_at);
    }

    public function test_the_invoice_shows_lines_payments_and_balance_to_staff_only(): void
    {
        $booking = $this->booking();
        $booking->recordPayment(300_000, BookingPaymentMethod::Card, BookingPaymentKind::Deposit, 'REF-9');

        $this->actingAs($this->admin())
            ->get(route('bookings.invoice', $booking))
            ->assertOk()
            ->assertSee($booking->reference_code)
            ->assertSee('REF-9')
            ->assertSee('مانده‌ی قابل پرداخت');

        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'بدون دسترسی صورت‌حساب']));
        $this->actingAs($user)->get(route('bookings.invoice', $booking))->assertForbidden();
    }
}
