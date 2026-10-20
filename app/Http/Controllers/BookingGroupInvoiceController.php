<?php

namespace App\Http\Controllers;

use App\Models\BookingGroup;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Printable statement of a whole group: every room's folio lines and the
 * group's total, paid and balance.
 */
class BookingGroupInvoiceController extends Controller
{
    public function show(Request $request, BookingGroup $group): View
    {
        abort_unless($request->user()?->can('view', $group), 403, 'شما مجاز به دیدن صورت‌حساب گروه نیستید.');

        $group->load(['guest', 'bookings.room.roomType', 'bookings.charges', 'bookings.payments']);

        return view('booking-group-invoice', ['group' => $group, 'bookings' => $group->bookings->sortBy('check_in_date')->values()]);
    }
}
