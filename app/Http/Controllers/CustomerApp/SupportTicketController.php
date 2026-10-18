<?php

namespace App\Http\Controllers\CustomerApp;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Guest;
use App\Models\GuestTicket;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "تیکت پشتیبانی" in the وب‌اپ مشتریان - a thin, customer-facing front end
 * over the same GuestTicket CRM support-ticket record staff already work
 * from in the admin panel (see app/Filament/Resources/GuestTickets), so a
 * ticket raised here shows up for staff with no separate inbox to check.
 */
class SupportTicketController extends Controller
{
    public function index(): View
    {
        /** @var Guest $guest */
        $guest = Auth::guard('customer')->user();

        return view('customer-app-support', [
            'guest' => $guest,
            'tickets' => $guest->tickets()->latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var Guest $guest */
        $guest = Auth::guard('customer')->user();

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        $guest->tickets()->create([
            'subject' => $data['subject'],
            'description' => $data['description'] ?? null,
            'status' => TicketStatus::Open,
            'priority' => TicketPriority::Normal,
        ]);

        return redirect()->route('customer-app.support')->with('success', 'تیکت شما ثبت شد. تیم پشتیبانی به‌زودی پاسخ می‌دهد.');
    }

    public function show(GuestTicket $ticket): View
    {
        /** @var Guest $guest */
        $guest = Auth::guard('customer')->user();

        // 404 rather than 403 on a ticket that isn't this guest's own, so
        // the response gives no hint that the id belongs to someone else.
        abort_unless($ticket->guest_id === $guest->id, 404);

        return view('customer-app-support-show', [
            'guest' => $guest,
            'ticket' => $ticket->load('replies.guest', 'replies.user'),
        ]);
    }

    public function reply(Request $request, GuestTicket $ticket): RedirectResponse
    {
        /** @var Guest $guest */
        $guest = Auth::guard('customer')->user();

        abort_unless($ticket->guest_id === $guest->id, 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $ticket->replies()->create([
            'guest_id' => $guest->id,
            'body' => $data['body'],
        ]);

        // A customer reply means the conversation is active again - a
        // ticket staff had already marked resolved/closed shouldn't stay
        // that way once the guest has come back with something new to say.
        if (in_array($ticket->status, [TicketStatus::Resolved, TicketStatus::Closed], true)) {
            $ticket->update(['status' => TicketStatus::InProgress]);
        }

        return redirect()->route('customer-app.support.show', $ticket)->with('success', 'پاسخ شما ثبت شد.');
    }

    public function rate(Request $request, GuestTicket $ticket): RedirectResponse
    {
        /** @var Guest $guest */
        $guest = Auth::guard('customer')->user();

        abort_unless($ticket->guest_id === $guest->id, 404);

        if (! $ticket->canBeRated()) {
            return back()->withErrors(['csat_score' => 'پس از حل‌شدن تیکت می‌توانید به پشتیبانی امتیاز دهید.']);
        }

        $data = $request->validate([
            'csat_score' => ['required', 'integer', 'between:1,5'],
            'csat_comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $ticket->update([
            'csat_score' => $data['csat_score'],
            'csat_comment' => $data['csat_comment'] ?? null,
            'csat_responded_at' => now(),
        ]);

        return back()->with('success', 'ممنون از امتیازتان!');
    }
}
