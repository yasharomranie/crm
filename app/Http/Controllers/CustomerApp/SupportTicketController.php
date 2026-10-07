<?php

namespace App\Http\Controllers\CustomerApp;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Guest;
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
}
