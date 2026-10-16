<?php

namespace App\Domain\Crm;

use App\Enums\TicketStatus;
use App\Models\GuestTicket;
use App\Models\User;
use App\Notifications\TicketSlaBreachedNotification;

/**
 * Alerts the right people the first time an open support ticket passes its
 * SLA deadline - the ticket's assignee, or every admin when nobody is
 * assigned - instead of the breach only being visible to whoever happens to
 * open the tickets table. Once per ticket (sla_breach_notified_at).
 */
class TicketSlaBreachService
{
    public function notifyBreachedTickets(): int
    {
        $tickets = GuestTicket::query()
            ->whereIn('status', [TicketStatus::Open, TicketStatus::InProgress])
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<=', now())
            ->whereNull('sla_breach_notified_at')
            ->with(['guest', 'assignee'])
            ->get();

        if ($tickets->isEmpty()) {
            return 0;
        }

        $admins = User::query()->whereHas('roles', fn ($query) => $query->where('name', 'admin'))->get();

        foreach ($tickets as $ticket) {
            $recipients = $ticket->assignee ? collect([$ticket->assignee]) : $admins;

            foreach ($recipients as $recipient) {
                $recipient->notify(new TicketSlaBreachedNotification($ticket));
            }

            $ticket->update(['sla_breach_notified_at' => now()]);
        }

        return $tickets->count();
    }
}
