<?php

namespace App\Domain\Crm;

use App\Enums\MessageDeliveryStatus;
use App\Enums\WalletTransactionType;
use App\Models\Guest;
use App\Models\WalletSetting;
use App\Models\WalletTransaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Morilog\Jalali\Jalalian;

/**
 * Expiry of earned (cashback) credit.
 *
 * The ledger has no per-credit "used" counter, so how much of each credit
 * is still unspent is worked out by replaying the guest's transactions in
 * order: every ordinary debit spends the soonest-to-expire credit first
 * (credits that never expire last), and an expiry debit only ever reduces
 * the one credit it names (source_transaction_id). That is the customer-
 * friendly order - what would expire soonest is spent first - and makes the
 * amount that really expires exactly what was left of that credit.
 */
class WalletExpiryService
{
    /**
     * @return Collection<int, array{id: int, remaining: int, expires_at: ?CarbonInterface}> keyed by credit id
     */
    public function unspentCredits(Guest $guest): Collection
    {
        $lots = [];

        $transactions = $guest->walletTransactions()->orderBy('created_at')->orderBy('id')->get();

        foreach ($transactions as $transaction) {
            $amount = (int) $transaction->amount;

            if ($transaction->type === WalletTransactionType::Credit) {
                $lots[$transaction->id] = ['id' => $transaction->id, 'remaining' => $amount, 'expires_at' => $transaction->expires_at];

                continue;
            }

            if ($transaction->source_transaction_id !== null) {
                if (isset($lots[$transaction->source_transaction_id])) {
                    $lots[$transaction->source_transaction_id]['remaining'] = max(0, $lots[$transaction->source_transaction_id]['remaining'] - $amount);
                }

                continue;
            }

            $order = collect($lots)
                ->sortBy([
                    fn ($a, $b) => ($a['expires_at']?->getTimestamp() ?? PHP_INT_MAX) <=> ($b['expires_at']?->getTimestamp() ?? PHP_INT_MAX),
                    fn ($a, $b) => $a['id'] <=> $b['id'],
                ])
                ->keys();

            foreach ($order as $lotId) {
                if ($amount <= 0) {
                    break;
                }

                $spent = min($amount, $lots[$lotId]['remaining']);
                $lots[$lotId]['remaining'] -= $spent;
                $amount -= $spent;
            }
        }

        return collect($lots);
    }

    /**
     * Takes back the unspent part of every credit whose validity has ended.
     *
     * @return int total toman expired
     */
    public function expireDue(): int
    {
        $guestIds = WalletTransaction::query()
            ->where('type', WalletTransactionType::Credit)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->whereNull('expiry_processed_at')
            ->distinct()
            ->pluck('guest_id');

        $expiredTotal = 0;

        foreach ($guestIds as $guestId) {
            $expiredTotal += DB::transaction(function () use ($guestId) {
                $guest = Guest::query()->lockForUpdate()->find($guestId);

                if (! $guest) {
                    return 0;
                }

                $unspent = $this->unspentCredits($guest);
                $total = 0;

                $dueCredits = $guest->walletTransactions()
                    ->where('type', WalletTransactionType::Credit)
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<=', now())
                    ->whereNull('expiry_processed_at')
                    ->get();

                foreach ($dueCredits as $credit) {
                    $remaining = (int) ($unspent[$credit->id]['remaining'] ?? 0);

                    if ($remaining > 0) {
                        $guest->debitWallet($remaining, "انقضای اعتبار کش‌بک ({$credit->reason})", null, $credit->id);
                        $total += $remaining;
                    }

                    $credit->update(['expiry_processed_at' => now()]);
                }

                return $total;
            });
        }

        return $expiredTotal;
    }

    /**
     * One SMS per guest, sent once per credit, a few days before the unspent
     * part of it expires. A credit is only marked as reminded when the
     * message actually went out, so a failed send is retried next run.
     *
     * @return int number of guests reminded
     */
    public function sendExpiryReminders(): int
    {
        $reminderDays = WalletSetting::current()->expiry_reminder_days;

        if ($reminderDays <= 0) {
            return 0;
        }

        $guestIds = WalletTransaction::query()
            ->where('type', WalletTransactionType::Credit)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays($reminderDays)])
            ->whereNull('expiry_reminded_at')
            ->whereNull('expiry_processed_at')
            ->distinct()
            ->pluck('guest_id');

        $reminded = 0;

        foreach ($guestIds as $guestId) {
            $guest = Guest::find($guestId);

            if (! $guest) {
                continue;
            }

            $unspent = $this->unspentCredits($guest);
            $credits = $guest->walletTransactions()
                ->where('type', WalletTransactionType::Credit)
                ->whereNotNull('expires_at')
                ->whereBetween('expires_at', [now(), now()->addDays($reminderDays)])
                ->whereNull('expiry_reminded_at')
                ->whereNull('expiry_processed_at')
                ->get();

            $amount = (int) $credits->sum(fn (WalletTransaction $credit) => $unspent[$credit->id]['remaining'] ?? 0);

            if ($amount <= 0) {
                $credits->each->update(['expiry_reminded_at' => now()]);

                continue;
            }

            $earliest = $credits->min('expires_at');
            $message = app(GuestMessenger::class)->sendSms($guest, sprintf(
                '%s گرامی، %s تومان از اعتبار کیف پول شما تا %s منقضی می‌شود. پیش از آن از اعتبار خود استفاده کنید.',
                $guest->name,
                Number::toPersian(number_format($amount)),
                Jalalian::fromCarbon($earliest)->format('Y/m/d'),
            ));

            if ($message->status === MessageDeliveryStatus::Sent) {
                $credits->each->update(['expiry_reminded_at' => now()]);
                $reminded++;
            }
        }

        return $reminded;
    }
}
