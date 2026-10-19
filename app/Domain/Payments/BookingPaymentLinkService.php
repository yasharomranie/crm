<?php

namespace App\Domain\Payments;

use App\Enums\BookingPaymentKind;
use App\Enums\BookingPaymentMethod;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingPaymentLink;
use App\Models\PaymentGatewaySetting;
use Illuminate\Support\Facades\DB;

/**
 * Online payment of a booking through the payment gateway: staff make a link
 * for an amount up to the unpaid balance, the guest pays on the gateway page,
 * and the booking's money ledger gets an «online» payment only after the
 * gateway itself confirms it - once, however many times the callback opens.
 * A paid link also confirms a booking that was only held.
 */
class BookingPaymentLinkService
{
    public function __construct(private readonly ZarinpalGateway $gateway) {}

    /**
     * An open link for $amount (the whole balance by default). An already open
     * link for the same amount is reused, so the URL staff sent stays valid.
     *
     * @throws GatewayException
     */
    public function linkFor(Booking $booking, ?int $amount = null, ?int $userId = null): BookingPaymentLink
    {
        if (! PaymentGatewaySetting::current()->isUsable()) {
            throw new GatewayException('پرداخت آنلاین در حال حاضر فعال نیست؛ از «تنظیمات درگاه پرداخت» آن را فعال کنید.');
        }

        if (! in_array($booking->status, [BookingStatus::Pending, BookingStatus::Confirmed, BookingStatus::CheckedIn], true)) {
            throw new GatewayException('برای این رزرو نمی‌توان لینک پرداخت ساخت.');
        }

        $balance = $booking->fresh(['charges', 'payments'])->balanceDue();
        $amount ??= $balance;

        if ($amount <= 0 || $amount > $balance) {
            throw new GatewayException($balance > 0
                ? 'مبلغ باید بین ۱ و مانده‌ی صورت‌حساب ('.number_format($balance).' تومان) باشد.'
                : 'این رزرو مانده‌ای ندارد.');
        }

        $existing = $booking->paymentLinks()
            ->where('status', BookingPaymentLink::STATUS_PENDING)
            ->where('amount', $amount)
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        return $existing ?? $booking->paymentLinks()->create(['amount' => $amount, 'created_by' => $userId]);
    }

    /**
     * @return string URL of the gateway payment page
     *
     * @throws GatewayException
     */
    public function start(BookingPaymentLink $link, string $callbackUrl): string
    {
        if (! $link->isOpen()) {
            throw new GatewayException('این لینک پرداخت منقضی شده یا قبلاً استفاده شده است.');
        }

        if (! PaymentGatewaySetting::current()->isUsable()) {
            throw new GatewayException('پرداخت آنلاین در حال حاضر فعال نیست.');
        }

        $balance = $link->booking->fresh(['charges', 'payments'])->balanceDue();

        if ($balance <= 0) {
            throw new GatewayException('صورت‌حساب این رزرو تسویه شده است.');
        }

        try {
            $authority = $this->gateway->requestPayment(min($link->amount, $balance), $callbackUrl, 'پرداخت رزرو '.$link->booking->reference_code);
        } catch (GatewayException $exception) {
            $link->update(['error' => $exception->getMessage()]);

            throw $exception;
        }

        $link->update(['authority' => $authority, 'amount' => min($link->amount, $balance), 'error' => null]);

        return $this->gateway->startPayUrl($authority);
    }

    /**
     * @param  string  $gatewayStatus  the "Status" query value the gateway sent back ("OK" or "NOK")
     */
    public function complete(string $authority, string $gatewayStatus): ?BookingPaymentLink
    {
        return DB::transaction(function () use ($authority, $gatewayStatus) {
            $link = BookingPaymentLink::query()->where('authority', $authority)->lockForUpdate()->first();

            if (! $link || $link->status !== BookingPaymentLink::STATUS_PENDING) {
                return $link;
            }

            if (strtoupper($gatewayStatus) !== 'OK') {
                $link->update(['error' => 'پرداخت توسط مهمان لغو شد یا انجام نشد.', 'authority' => null]);

                return $link;
            }

            try {
                $receipt = $this->gateway->verify($link->amount, $authority);
            } catch (GatewayException $exception) {
                // An unreachable gateway keeps the link pending so reopening
                // the callback can try again; a refusal frees the link for a
                // fresh attempt.
                if (! $exception->isTransient) {
                    $link->update(['error' => $exception->getMessage(), 'authority' => null]);
                }

                return $link;
            }

            $this->book($link, $receipt['ref_id']);

            $link->update([
                'status' => BookingPaymentLink::STATUS_PAID,
                'ref_id' => $receipt['ref_id'],
                'card_pan' => $receipt['card_pan'],
                'paid_at' => now(),
                'error' => null,
            ]);

            return $link;
        });
    }

    /**
     * Writes the confirmed money into the booking: up to the balance as an
     * online payment, anything beyond it (staff took cash meanwhile) into the
     * guest's wallet, and a held booking becomes confirmed.
     */
    private function book(BookingPaymentLink $link, string $refId): void
    {
        $booking = Booking::query()->lockForUpdate()->with(['charges', 'payments', 'guest'])->findOrFail($link->booking_id);

        $applied = min($link->amount, $booking->balanceDue());

        if ($applied > 0) {
            $booking->recordPayment($applied, BookingPaymentMethod::Online, BookingPaymentKind::Payment, $refId, 'پرداخت آنلاین از لینک پرداخت');
        }

        if ($link->amount > $applied) {
            $booking->guest->creditWallet($link->amount - $applied, "مازاد پرداخت آنلاین رزرو {$booking->reference_code}", $booking);
        }

        if ($booking->status === BookingStatus::Pending) {
            $booking->update(['status' => BookingStatus::Confirmed, 'hold_expires_at' => null]);
        }
    }
}
