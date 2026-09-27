<?php

namespace App\Domain\Ticketing;

use App\Enums\AdmissionTicketStatus;
use App\Models\AdmissionTicket;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The AdmissionTicketStatus::Refunded case and GateValidationService's own
 * rejection for it already existed, but nothing ever actually produced
 * that state - this is that missing write path, mirrored on the same
 * "one Refund record per ticket" shape the Refund model itself already
 * assumes (order_id + admission_ticket_id).
 */
class RefundService
{
    /**
     * A ticket's own price isn't stored on it directly - it's the
     * OrderItem's final_amount split evenly across every ticket that item
     * produced (quantity > 1 tickets from one line all cost the same).
     */
    public function refundTicket(
        AdmissionTicket $ticket,
        ?string $reason = null,
        ?string $reference = null,
        ?User $refundedBy = null,
        ?int $amountOverride = null,
    ): Refund {
        if (in_array($ticket->status, [AdmissionTicketStatus::Refunded, AdmissionTicketStatus::Cancelled], true)) {
            throw new TicketingException('این بلیت قبلاً لغو یا مسترد شده است.');
        }

        return DB::transaction(function () use ($ticket, $reason, $reference, $refundedBy, $amountOverride) {
            $locked = AdmissionTicket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, [AdmissionTicketStatus::Refunded, AdmissionTicketStatus::Cancelled], true)) {
                throw new TicketingException('این بلیت قبلاً لغو یا مسترد شده است.');
            }

            $orderItem = $locked->orderItem;
            $amount = $amountOverride ?? intdiv($orderItem->final_amount, max(1, $orderItem->quantity));

            $refund = Refund::create([
                'order_id' => $orderItem->order_id,
                'admission_ticket_id' => $locked->id,
                'amount' => $amount,
                'reason' => $reason,
                'reference' => $reference,
                'refunded_by' => $refundedBy?->id,
                'refunded_at' => now(),
            ]);

            $locked->update(['status' => AdmissionTicketStatus::Refunded]);

            return $refund;
        });
    }
}
