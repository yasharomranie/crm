<?php

namespace App\Http\Controllers\CustomerApp;

use App\Domain\Ticketing\TicketingException;
use App\Domain\Ticketing\TicketIssuer;
use App\Enums\PaymentMethod;
use App\Enums\TicketSourceType;
use App\Exceptions\InsufficientWalletBalanceException;
use App\Http\Controllers\Controller;
use App\Models\AdmissionTicket;
use App\Models\Guest;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * "گیشه بلیط" from inside the وب‌اپ مشتریان - a signed-in Guest buys an
 * event-admission ticket through the exact same TicketIssuer the Cashier
 * page and external POS API use (so it prints/scans identically), paid
 * for out of their own wallet balance instead of cash or a card terminal
 * (no online payment gateway is connected yet - see Guest::debitWallet()).
 * Tickets have no guest_id column (see AdmissionTicket/Order), so "بلیط‌های
 * من" matches by customer_phone against the signed-in guest's own mobile,
 * the same phone number every ticket bought here is stamped with.
 */
class TicketController extends Controller
{
    public function __construct(private readonly TicketIssuer $issuer) {}

    public function index(): View
    {
        /** @var Guest $guest */
        $guest = Auth::guard('customer')->user();

        return view('customer-app-tickets', [
            'guest' => $guest,
            'products' => Product::where('is_active', true)
                ->with(['ticketVariants' => fn ($query) => $query->where('is_active', true)])
                ->get(),
            // Laravel's query builder turns where('x', null) into a
            // "whereNull" - never run this query when the guest has no
            // mobile (a Google-only account), or it would wrongly match
            // every phoneless ticket in the system instead of none.
            'myTickets' => $guest->mobile
                ? AdmissionTicket::where('customer_phone', $guest->mobile)
                    ->with(['ticketVariant.product', 'qrToken'])
                    ->latest()
                    ->get()
                : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var Guest $guest */
        $guest = Auth::guard('customer')->user();

        $data = $request->validate([
            'ticket_variant_id' => ['required', 'integer'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:10'],
        ]);

        try {
            DB::transaction(function () use ($data, $guest) {
                $order = $this->issuer->issue([
                    'source_type' => TicketSourceType::Online,
                    'items' => [['ticket_variant_id' => $data['ticket_variant_id'], 'quantity' => $data['quantity'] ?? 1]],
                    'customer_name' => $guest->name,
                    'customer_phone' => $guest->mobile,
                ]);

                $guest->debitWallet((int) $order->final_amount, "خرید بلیط آنلاین - {$order->order_number}");

                $order->payments()->create([
                    'method' => PaymentMethod::Wallet,
                    'amount' => $order->final_amount,
                    'status' => 'paid',
                    'paid_at' => now(),
                    'metadata' => ['guest_id' => $guest->id],
                ]);
                $order->update(['amount_paid' => $order->final_amount]);
            });
        } catch (TicketingException $exception) {
            return back()->withErrors(['ticket_variant_id' => $exception->getMessage()]);
        } catch (InsufficientWalletBalanceException) {
            return back()->withErrors(['ticket_variant_id' => 'موجودی کیف پول شما برای این خرید کافی نیست.']);
        }

        return redirect()->route('customer-app.tickets')->with('success', 'بلیط شما با موفقیت صادر شد.');
    }
}
