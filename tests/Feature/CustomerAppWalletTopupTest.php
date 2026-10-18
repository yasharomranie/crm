<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\PaymentGatewaySetting;
use App\Models\WalletTopup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CustomerAppWalletTopupTest extends TestCase
{
    use RefreshDatabase;

    private function enableGateway(array $overrides = []): void
    {
        PaymentGatewaySetting::current()->update([
            'is_enabled' => true,
            'merchant_id' => 'test-merchant',
            'min_topup_amount' => 10_000,
            'max_topup_amount' => 5_000_000,
            ...$overrides,
        ]);
    }

    private function pendingTopup(Guest $guest, int $amount = 200_000, string $authority = 'A00000000000000000000000000000001'): WalletTopup
    {
        return WalletTopup::create(['guest_id' => $guest->id, 'amount' => $amount, 'authority' => $authority]);
    }

    public function test_the_topup_form_is_only_shown_when_the_gateway_is_usable(): void
    {
        $guest = Guest::factory()->create();

        $this->actingAs($guest, 'customer')->get(route('customer-app.wallet'))->assertDontSee('پرداخت آنلاین');

        $this->enableGateway();

        $this->actingAs($guest, 'customer')->get(route('customer-app.wallet'))->assertSee('پرداخت آنلاین');
    }

    public function test_starting_a_topup_sends_the_customer_to_the_gateway_with_the_amount_in_rials(): void
    {
        $this->enableGateway();
        Http::fake(['*' => Http::response(['data' => ['code' => 100, 'authority' => 'A0000000000000000000000000000000AB']])]);
        $guest = Guest::factory()->create();

        $response = $this->actingAs($guest, 'customer')->post(route('customer-app.wallet.topup'), ['amount' => 150_000]);

        $response->assertRedirect('https://www.zarinpal.com/pg/StartPay/A0000000000000000000000000000000AB');
        $sent = Http::recorded()->map(fn (array $pair) => $pair[0]->data())->first();
        $this->assertSame(1_500_000, $sent['amount'] ?? null);
        $this->assertSame('test-merchant', $sent['merchant_id'] ?? null);
        $this->assertSame(WalletTopup::STATUS_PENDING, WalletTopup::sole()->status);
        $this->assertSame(0, (int) $guest->fresh()->wallet_balance);
    }

    public function test_an_amount_outside_the_allowed_range_never_reaches_the_gateway(): void
    {
        $this->enableGateway();
        Http::fake();
        $guest = Guest::factory()->create();

        $this->actingAs($guest, 'customer')
            ->post(route('customer-app.wallet.topup'), ['amount' => 500])
            ->assertRedirect(route('customer-app.wallet'))
            ->assertSessionHasErrors('amount');

        Http::assertNothingSent();
        $this->assertSame(0, WalletTopup::count());
    }

    public function test_a_confirmed_payment_credits_the_wallet_exactly_once(): void
    {
        $this->enableGateway();
        Http::fake(['*' => Http::response(['data' => ['code' => 100, 'ref_id' => 123456, 'card_pan' => '6037****1234']])]);
        $guest = Guest::factory()->create(['wallet_balance' => 1_000]);
        $topup = $this->pendingTopup($guest, 200_000, 'AUTH-1');

        $callback = route('customer-app.wallet.topup.callback', ['Authority' => 'AUTH-1', 'Status' => 'OK']);
        $this->get($callback)->assertRedirect(route('customer-app.wallet'));
        $this->get($callback)->assertRedirect(route('customer-app.wallet'));

        $this->assertSame(201_000, (int) $guest->fresh()->wallet_balance);
        $this->assertSame(WalletTopup::STATUS_PAID, $topup->fresh()->status);
        $this->assertSame('123456', $topup->fresh()->ref_id);
        $this->assertSame(1, $guest->walletTransactions()->count());
        Http::assertSentCount(1);
    }

    public function test_a_cancelled_payment_credits_nothing_and_skips_verification(): void
    {
        $this->enableGateway();
        Http::fake();
        $guest = Guest::factory()->create(['wallet_balance' => 0]);
        $topup = $this->pendingTopup($guest, 200_000, 'AUTH-2');

        $this->get(route('customer-app.wallet.topup.callback', ['Authority' => 'AUTH-2', 'Status' => 'NOK']));

        $this->assertSame(0, (int) $guest->fresh()->wallet_balance);
        $this->assertSame(WalletTopup::STATUS_FAILED, $topup->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_a_payment_the_gateway_does_not_confirm_credits_nothing(): void
    {
        $this->enableGateway();
        Http::fake(['*' => Http::response(['data' => [], 'errors' => ['code' => -51, 'message' => 'پرداخت ناموفق']], 200)]);
        $guest = Guest::factory()->create(['wallet_balance' => 0]);
        $topup = $this->pendingTopup($guest, 200_000, 'AUTH-3');

        $this->get(route('customer-app.wallet.topup.callback', ['Authority' => 'AUTH-3', 'Status' => 'OK']));

        $this->assertSame(0, (int) $guest->fresh()->wallet_balance);
        $this->assertSame(WalletTopup::STATUS_FAILED, $topup->fresh()->status);
    }

    public function test_an_unreachable_gateway_leaves_the_topup_pending_and_retryable(): void
    {
        $this->enableGateway();
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            if (++$attempts === 1) {
                throw new ConnectionException('timeout');
            }

            return Http::response(['data' => ['code' => 100, 'ref_id' => 777]]);
        });
        $guest = Guest::factory()->create(['wallet_balance' => 0]);
        $topup = $this->pendingTopup($guest, 200_000, 'AUTH-4');

        $this->get(route('customer-app.wallet.topup.callback', ['Authority' => 'AUTH-4', 'Status' => 'OK']));

        $this->assertSame(0, (int) $guest->fresh()->wallet_balance);
        $this->assertSame(WalletTopup::STATUS_PENDING, $topup->fresh()->status);

        $this->get(route('customer-app.wallet.topup.callback', ['Authority' => 'AUTH-4', 'Status' => 'OK']));

        $this->assertSame(200_000, (int) $guest->fresh()->wallet_balance);
    }

    public function test_an_unknown_authority_is_ignored(): void
    {
        $this->get(route('customer-app.wallet.topup.callback', ['Authority' => 'NOPE', 'Status' => 'OK']))
            ->assertRedirect(route('customer-app.wallet'));

        $this->assertSame(0, WalletTopup::count());
    }
}
