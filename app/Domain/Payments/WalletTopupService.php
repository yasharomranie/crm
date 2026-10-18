<?php

namespace App\Domain\Payments;

use App\Models\Guest;
use App\Models\PaymentGatewaySetting;
use App\Models\WalletTopup;
use Illuminate\Support\Facades\DB;

/**
 * Online wallet top-up: start() creates the attempt and returns the
 * gateway page to send the customer to; complete() runs when the customer
 * comes back and credits the wallet only after the gateway itself
 * confirms the money was paid - and only once, however many times the
 * callback is opened.
 */
class WalletTopupService
{
    public function __construct(private readonly ZarinpalGateway $gateway) {}

    /**
     * @return string URL of the gateway payment page
     *
     * @throws GatewayException
     */
    public function start(Guest $guest, int $amount, string $callbackUrl): string
    {
        $settings = PaymentGatewaySetting::current();

        if (! $settings->isUsable()) {
            throw new GatewayException('پرداخت آنلاین در حال حاضر فعال نیست.');
        }

        if ($amount < $settings->min_topup_amount || $amount > $settings->max_topup_amount) {
            throw new GatewayException(sprintf('مبلغ شارژ باید بین %s و %s تومان باشد.', number_format($settings->min_topup_amount), number_format($settings->max_topup_amount)));
        }

        $topup = WalletTopup::create(['guest_id' => $guest->id, 'amount' => $amount]);

        try {
            $authority = $this->gateway->requestPayment($amount, $callbackUrl, 'شارژ کیف پول');
        } catch (GatewayException $exception) {
            $topup->update(['status' => WalletTopup::STATUS_FAILED, 'error' => $exception->getMessage()]);

            throw $exception;
        }

        $topup->update(['authority' => $authority]);

        return $this->gateway->startPayUrl($authority);
    }

    /**
     * @param  string  $gatewayStatus  the "Status" query value the gateway sent back ("OK" or "NOK")
     */
    public function complete(string $authority, string $gatewayStatus): ?WalletTopup
    {
        return DB::transaction(function () use ($authority, $gatewayStatus) {
            $topup = WalletTopup::query()->where('authority', $authority)->lockForUpdate()->first();

            if (! $topup || $topup->status !== WalletTopup::STATUS_PENDING) {
                return $topup;
            }

            if (strtoupper($gatewayStatus) !== 'OK') {
                $topup->update(['status' => WalletTopup::STATUS_FAILED, 'error' => 'پرداخت توسط مشتری لغو شد یا انجام نشد.']);

                return $topup;
            }

            try {
                $receipt = $this->gateway->verify($topup->amount, $authority);
            } catch (GatewayException $exception) {
                // The gateway could not be asked, or refused: leave nothing
                // credited. A refusal is final; an unreachable gateway stays
                // pending so reopening the callback can try again.
                if ($exception->isTransient) {
                    return $topup;
                }

                $topup->update(['status' => WalletTopup::STATUS_FAILED, 'error' => $exception->getMessage()]);

                return $topup;
            }

            $topup->guest->creditWallet($topup->amount, 'شارژ آنلاین - کد پیگیری '.$receipt['ref_id']);
            $topup->update([
                'status' => WalletTopup::STATUS_PAID,
                'ref_id' => $receipt['ref_id'],
                'card_pan' => $receipt['card_pan'],
                'paid_at' => now(),
                'error' => null,
            ]);

            return $topup;
        });
    }
}
