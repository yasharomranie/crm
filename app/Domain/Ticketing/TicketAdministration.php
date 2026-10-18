<?php

namespace App\Domain\Ticketing;

use App\Enums\AdmissionTicketStatus;
use App\Models\AdmissionTicket;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Staff actions on tickets that are already sold: block a ticket at the
 * gates (and lift the block), and refund every ticket of an order that has
 * not been used.
 */
class TicketAdministration
{
    public function __construct(private readonly RefundService $refunds) {}

    public function block(AdmissionTicket $ticket, string $reason): void
    {
        $ticket = $ticket->fresh();

        if (in_array($ticket->status, [AdmissionTicketStatus::Cancelled, AdmissionTicketStatus::Refunded, AdmissionTicketStatus::Blocked], true)) {
            throw new TicketingException('این بلیت در وضعیتی است که مسدود کردنش معنا ندارد.');
        }

        $ticket->update(['status' => AdmissionTicketStatus::Blocked, 'blocked_reason' => $reason]);
    }

    public function unblock(AdmissionTicket $ticket): void
    {
        $ticket = $ticket->fresh();

        if ($ticket->status !== AdmissionTicketStatus::Blocked) {
            throw new TicketingException('این بلیت مسدود نیست.');
        }

        $status = match (true) {
            $ticket->used_uses <= 0 => AdmissionTicketStatus::Active,
            $ticket->remaining_uses > 0 => AdmissionTicketStatus::PartiallyUsed,
            default => AdmissionTicketStatus::Used,
        };

        $ticket->update(['status' => $status, 'blocked_reason' => null]);
    }

    /**
     * Refunds every ticket of the order that is still unused (used, blocked,
     * cancelled and already refunded ones are left alone).
     *
     * @return int number of tickets refunded
     */
    public function refundUnused(Order $order, ?string $reason, ?User $by): int
    {
        return DB::transaction(function () use ($order, $reason, $by) {
            $count = 0;

            foreach ($order->tickets()->where('admission_tickets.status', AdmissionTicketStatus::Active)->get() as $ticket) {
                $this->refunds->refundTicket($ticket, $reason, null, $by);
                $count++;
            }

            return $count;
        });
    }
}
