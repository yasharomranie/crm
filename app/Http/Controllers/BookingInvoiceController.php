<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Printable statement of one booking for the guest: the folio lines, the
 * money received, refunds and what is still owed.
 */
class BookingInvoiceController extends Controller
{
    public function show(Request $request, Booking $booking): View
    {
        abort_unless($request->user()?->can('view', $booking), 403, 'شما مجاز به دیدن صورت‌حساب رزرو نیستید.');

        $booking->load(['guest', 'room.roomType', 'charges', 'payments', 'damageDeposit']);

        return view('booking-invoice', ['booking' => $booking]);
    }
}
