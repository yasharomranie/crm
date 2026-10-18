<?php

namespace App\Domain\Ticketing;

use App\Enums\AdmissionTicketStatus;
use App\Enums\PaymentMethod;
use App\Enums\TicketSourceType;
use App\Models\AdmissionTicket;
use App\Models\ComplimentaryIssue;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\TicketUsage;
use App\Models\WalletTopup;
use App\Models\WebhookSubscription;
use Illuminate\Support\Carbon;

/**
 * The box office's numbers for a period. A free (complimentary) ticket is
 * never counted as a sale - it has its own section - and a 100%-discounted
 * sale stays a sale with a discount, so the two can never be mixed up.
 */
class TicketingReport
{
    /**
     * @return array<string, mixed>
     */
    public function build(Carbon $from, Carbon $to): array
    {
        return [
            'from' => $from,
            'to' => $to,
            'sales' => $this->sales($from, $to),
            'discounts' => $this->discounts($from, $to),
            'unused' => $this->unusedExpired($from, $to),
            'complimentary' => $this->complimentary($from, $to),
            'entry_by_hour' => $this->entryByHour($from, $to),
            'reconciliation' => $this->reconciliation($from, $to),
        ];
    }

    /**
     * @return array{orders: int, tickets: int, gross: int, discount: int, net: int, by_product: list<array{name: string, tickets: int, net: int}>, by_cashier: list<array{name: string, orders: int, net: int}>, by_method: list<array{method: string, amount: int}>, by_source: list<array{source: string, orders: int, net: int}>}
     */
    private function sales(Carbon $from, Carbon $to): array
    {
        $orders = Order::query()
            ->where('orders.source_type', '!=', TicketSourceType::Complimentary)
            ->whereBetween('orders.created_at', [$from, $to]);

        $byProduct = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.source_type', '!=', TicketSourceType::Complimentary->value)
            ->whereBetween('orders.created_at', [$from, $to])
            ->groupBy('products.id', 'products.name')
            ->selectRaw('products.name as name, sum(order_items.quantity) as tickets, sum(order_items.final_amount) as net')
            ->orderByDesc('net')
            ->get()
            ->map(fn ($row) => ['name' => $row->name, 'tickets' => (int) $row->tickets, 'net' => (int) $row->net])
            ->all();

        $byCashier = (clone $orders)
            ->leftJoin('users', 'users.id', '=', 'orders.created_by')
            ->groupBy('orders.created_by', 'users.name')
            ->selectRaw('users.name as name, count(*) as orders, sum(orders.final_amount) as net')
            ->orderByDesc('net')
            ->get()
            ->map(fn ($row) => ['name' => $row->name ?? 'ثبت‌شده از API/سیستم', 'orders' => (int) $row->orders, 'net' => (int) $row->net])
            ->all();

        $bySource = (clone $orders)
            ->groupBy('orders.source_type')
            ->selectRaw('orders.source_type as source, count(*) as orders, sum(orders.final_amount) as net')
            ->get()
            ->map(fn ($row) => ['source' => TicketSourceType::tryFrom((string) $row->source)?->getLabel() ?? (string) $row->source, 'orders' => (int) $row->orders, 'net' => (int) $row->net])
            ->all();

        $byMethod = Payment::query()
            ->whereHas('order', fn ($query) => $query->where('source_type', '!=', TicketSourceType::Complimentary)->whereBetween('created_at', [$from, $to]))
            ->get()
            ->groupBy(fn (Payment $payment) => $payment->method instanceof PaymentMethod ? $payment->method->getLabel() : (string) $payment->method)
            ->map(fn ($payments, string $method) => ['method' => $method, 'amount' => (int) $payments->sum('amount')])
            ->values()
            ->all();

        return [
            'orders' => (clone $orders)->count(),
            'tickets' => (int) OrderItem::query()->whereHas('order', fn ($query) => $query->where('source_type', '!=', TicketSourceType::Complimentary)->whereBetween('created_at', [$from, $to]))->sum('quantity'),
            'gross' => (int) (clone $orders)->sum('orders.original_amount'),
            'discount' => (int) (clone $orders)->sum('orders.discount_amount'),
            'net' => (int) (clone $orders)->sum('orders.final_amount'),
            'by_product' => $byProduct,
            'by_cashier' => $byCashier,
            'by_method' => $byMethod,
            'by_source' => $bySource,
        ];
    }

    /**
     * @return list<array{code: string, orders: int, discount: int, net: int}>
     */
    private function discounts(Carbon $from, Carbon $to): array
    {
        return Order::query()
            ->whereNotNull('discount_code_snapshot')
            ->where('source_type', '!=', TicketSourceType::Complimentary)
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('discount_code_snapshot')
            ->selectRaw('discount_code_snapshot as code, count(*) as orders, sum(discount_amount) as discount, sum(final_amount) as net')
            ->orderByDesc('discount')
            ->get()
            ->map(fn ($row) => ['code' => $row->code, 'orders' => (int) $row->orders, 'discount' => (int) $row->discount, 'net' => (int) $row->net])
            ->all();
    }

    /**
     * Paid tickets sold in the period that expired without ever being
     * scanned - money taken for something the customer never used.
     *
     * @return array{tickets: int, value: int, by_product: list<array{name: string, tickets: int, value: int}>}
     */
    private function unusedExpired(Carbon $from, Carbon $to): array
    {
        $tickets = AdmissionTicket::query()
            ->with('orderItem.product')
            ->whereBetween('admission_tickets.created_at', [$from, $to])
            ->where('used_uses', 0)
            ->where('valid_until', '<', now())
            ->whereNotIn('status', [AdmissionTicketStatus::Cancelled, AdmissionTicketStatus::Refunded])
            ->where('source_type', '!=', TicketSourceType::Complimentary)
            ->get()
            ->filter(fn (AdmissionTicket $ticket) => $ticket->orderItem && $ticket->orderItem->quantity > 0);

        $value = fn (AdmissionTicket $ticket) => intdiv((int) $ticket->orderItem->final_amount, max(1, (int) $ticket->orderItem->quantity));

        return [
            'tickets' => $tickets->count(),
            'value' => (int) $tickets->sum($value),
            'by_product' => $tickets
                ->groupBy(fn (AdmissionTicket $ticket) => $ticket->orderItem->product?->name ?? '—')
                ->map(fn ($group, string $name) => ['name' => $name, 'tickets' => $group->count(), 'value' => (int) $group->sum($value)])
                ->sortByDesc('value')
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{tickets: int, nominal_value: int, by_reason: list<array{reason: string, tickets: int, nominal_value: int}>, by_issuer: list<array{name: string, tickets: int}>}
     */
    private function complimentary(Carbon $from, Carbon $to): array
    {
        $issues = ComplimentaryIssue::query()->with('issuer')->whereBetween('issued_at', [$from, $to])->get();

        return [
            'tickets' => (int) $issues->sum('quantity'),
            'nominal_value' => (int) $issues->sum('original_value'),
            'by_reason' => $issues->groupBy('reason')
                ->map(fn ($group, string $reason) => ['reason' => $reason, 'tickets' => (int) $group->sum('quantity'), 'nominal_value' => (int) $group->sum('original_value')])
                ->sortByDesc('tickets')->values()->all(),
            'by_issuer' => $issues->groupBy(fn (ComplimentaryIssue $issue) => $issue->issuer?->name ?? '—')
                ->map(fn ($group, string $name) => ['name' => $name, 'tickets' => (int) $group->sum('quantity')])
                ->sortByDesc('tickets')->values()->all(),
        ];
    }

    /**
     * Successful gate passes per hour of the day (Tehran time), for staffing.
     *
     * @return array<int, int> hour (0-23) => passes
     */
    private function entryByHour(Carbon $from, Carbon $to): array
    {
        $hours = array_fill(0, 24, 0);

        TicketUsage::query()
            ->whereBetween('created_at', [$from, $to])
            ->pluck('created_at')
            ->each(function ($createdAt) use (&$hours) {
                $hours[(int) Carbon::parse($createdAt)->setTimezone(config('app.timezone'))->format('G')]++;
            });

        return $hours;
    }

    /**
     * Things that need a human to look at them: online payments that carry
     * no gateway reference, wallet top-ups stuck or failed, and webhooks
     * that keep failing.
     *
     * @return array{online_without_reference: list<array{order_number: string, amount: int}>, stuck_topups: int, failed_topups: int, failing_webhooks: int}
     */
    private function reconciliation(Carbon $from, Carbon $to): array
    {
        return [
            'online_without_reference' => Payment::query()
                ->with('order')
                ->where('method', PaymentMethod::Online)
                ->where(fn ($query) => $query->whereNull('reference')->orWhere('reference', ''))
                ->whereBetween('created_at', [$from, $to])
                ->get()
                ->map(fn (Payment $payment) => ['order_number' => $payment->order?->order_number ?? '—', 'amount' => (int) $payment->amount])
                ->all(),
            'stuck_topups' => WalletTopup::query()->where('status', WalletTopup::STATUS_PENDING)->where('created_at', '<', now()->subHour())->count(),
            'failed_topups' => WalletTopup::query()->where('status', WalletTopup::STATUS_FAILED)->whereBetween('created_at', [$from, $to])->count(),
            'failing_webhooks' => WebhookSubscription::query()->where('is_active', true)->where('consecutive_failures', '>', 0)->count(),
        ];
    }
}
