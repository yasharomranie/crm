<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Printable day sheet for the cleaning team: rooms to turn over after
 * check-out, rooms that must be ready for arrivals (with party size, arrival
 * time and the guest's requests) and rooms with a guest staying over.
 */
class HousekeepingSheetController extends Controller
{
    public function show(Request $request): View
    {
        abort_unless($request->user()?->can('today-arrivals.access'), 403, 'شما مجاز به دیدن برگه‌ی نظافت نیستید.');

        $request->validate(['date' => ['nullable', 'date']]);

        $date = $request->filled('date') ? Carbon::parse($request->query('date'))->startOfDay() : today();

        return view('housekeeping-sheet', [
            'date' => $date,
            'departures' => $this->bookings()
                ->whereIn('status', [BookingStatus::CheckedIn, BookingStatus::CheckedOut])
                ->whereDate('check_out_date', $date)
                ->get()->sortBy('room.name')->values(),
            'arrivals' => $this->bookings()
                ->whereIn('status', [BookingStatus::Pending, BookingStatus::Confirmed, BookingStatus::CheckedIn])
                ->whereDate('check_in_date', $date)
                ->get()->sortBy('room.name')->values(),
            'stayovers' => $this->bookings()
                ->where('status', BookingStatus::CheckedIn)
                ->whereDate('check_in_date', '<', $date)
                ->whereDate('check_out_date', '>', $date)
                ->get()->sortBy('room.name')->values(),
        ]);
    }

    /**
     * @return Builder<Booking>
     */
    private function bookings()
    {
        return Booking::query()->with(['guest', 'room.roomType', 'precheckin']);
    }
}
