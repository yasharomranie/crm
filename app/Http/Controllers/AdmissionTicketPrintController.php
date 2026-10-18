<?php

namespace App\Http\Controllers;

use App\Enums\PrintType;
use App\Models\AdmissionTicket;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Renders a ticket using its own frozen ticket_template_version (never
 * the template's current/latest version - section 46-47) and logs every
 * view as a PrintLog. The FIRST log for a ticket is "original", every
 * one after that is "reprint" - reprinting only ever re-renders the same
 * AdmissionTicket/QrToken, it never creates a new one (section 23).
 */
class AdmissionTicketPrintController extends Controller
{
    public function show(Request $request, AdmissionTicket $ticket): View
    {
        // A printed ticket shows its QR secret, so being signed in is not
        // enough: only people who sell tickets at the desk may print them.
        abort_unless($request->user()?->can('cashier.access'), 403, 'شما مجاز به چاپ بلیت نیستید.');

        $ticket->load(['ticketVariant.product', 'qrToken', 'ticketTemplateVersion.elements', 'orderItem.order']);

        $isReprint = $ticket->printLogs()->exists();

        $ticket->printLogs()->create([
            'ticket_template_version_id' => $ticket->ticket_template_version_id,
            'printed_by' => $request->user()?->id,
            'print_type' => $isReprint ? PrintType::Reprint : PrintType::Original,
            'device' => $request->userAgent(),
            'printed_at' => now(),
        ]);

        return view('tickets.print', ['ticket' => $ticket]);
    }

    /**
     * Every ticket of one sale on one page (each rendered by the single
     * ticket page in an iframe, so templates and print logging behave
     * exactly as for a single print), with an optional auto-print and an
     * 80mm receipt-printer paper size.
     */
    public function order(Request $request, Order $order): View
    {
        abort_unless($request->user()?->can('cashier.access'), 403, 'شما مجاز به چاپ بلیت نیستید.');

        return view('tickets.print-order', [
            'order' => $order->load('items.tickets'),
            'tickets' => $order->items->flatMap(fn ($item) => $item->tickets)->values(),
            'autoPrint' => $request->boolean('auto'),
            'thermal' => $request->query('paper') === 'thermal',
        ]);
    }
}
