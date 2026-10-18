<?php

namespace App\Domain\Ticketing;

use App\Enums\PaymentMethod;
use App\Models\CashierShift;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Opening and closing a cashier's shift, and the arithmetic behind the
 * count at the end of it.
 *
 * Cash that should be in the drawer = the opening float + cash taken for
 * the shift's sales - refunds paid out of the drawer. Refunds are matched
 * by order: a refund made during the shift for an order that was paid
 * (even partly) in cash is assumed to have been handed back in cash.
 */
class CashierShiftService
{
    public function openFor(User $user): ?CashierShift
    {
        return CashierShift::query()->where('user_id', $user->id)->whereNull('closed_at')->latest('id')->first();
    }

    public function open(User $user, int $openingFloat): CashierShift
    {
        if ($openingFloat < 0) {
            throw new TicketingException('موجودی اول شیفت نمی‌تواند منفی باشد.');
        }

        return DB::transaction(function () use ($user, $openingFloat) {
            if ($this->openFor($user)) {
                throw new TicketingException('یک شیفت باز دارید؛ ابتدا آن را ببندید.');
            }

            return CashierShift::create(['user_id' => $user->id, 'opening_float' => $openingFloat, 'opened_at' => now()]);
        });
    }

    /**
     * @return array{by_method: array<string, int>, total_sales: int, cash_in: int, cash_refunds: int, expected_cash: int, orders: int}
     */
    public function summary(CashierShift $shift): array
    {
        $payments = Payment::query()
            ->whereHas('order', fn ($orders) => $orders->where('cashier_shift_id', $shift->id))
            ->get();

        $byMethod = [];

        foreach ($payments as $payment) {
            $key = $payment->method instanceof PaymentMethod ? $payment->method->value : (string) $payment->method;
            $byMethod[$key] = ($byMethod[$key] ?? 0) + (int) $payment->amount;
        }

        $cashIn = $byMethod[PaymentMethod::Cash->value] ?? 0;

        $cashPaidOrderIds = $payments
            ->filter(fn (Payment $payment) => ($payment->method instanceof PaymentMethod ? $payment->method->value : $payment->method) === PaymentMethod::Cash->value)
            ->pluck('order_id')
            ->unique();

        $cashRefunds = (int) Refund::query()
            ->whereIn('order_id', $cashPaidOrderIds)
            ->where('refunded_at', '>=', $shift->opened_at)
            ->where('refunded_at', '<=', $shift->closed_at ?? now())
            ->sum('amount');

        return [
            'by_method' => $byMethod,
            'total_sales' => (int) array_sum($byMethod),
            'cash_in' => $cashIn,
            'cash_refunds' => $cashRefunds,
            'expected_cash' => (int) $shift->opening_float + $cashIn - $cashRefunds,
            'orders' => $shift->orders()->count(),
        ];
    }

    public function close(CashierShift $shift, int $countedCash, ?string $notes = null): CashierShift
    {
        if (! $shift->isOpen()) {
            throw new TicketingException('این شیفت قبلاً بسته شده است.');
        }

        if ($countedCash < 0) {
            throw new TicketingException('مبلغ شمارش‌شده نمی‌تواند منفی باشد.');
        }

        $expected = $this->summary($shift)['expected_cash'];

        $shift->update([
            'closed_at' => now(),
            'expected_cash' => $expected,
            'counted_cash' => $countedCash,
            'difference' => $countedCash - $expected,
            'notes' => $notes,
        ]);

        return $shift;
    }
}
