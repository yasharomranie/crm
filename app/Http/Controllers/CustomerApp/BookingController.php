<?php

namespace App\Http\Controllers\CustomerApp;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Exceptions\RoomNotAvailableException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Room;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * "سفارش‌های من" in the وب‌اپ مشتریان - lets a signed-in Guest search real
 * room availability and book directly (reusing Booking's own availability
 * guard and charge/reference-code generation, the same machinery
 * Api\V1\External\BookingController uses for channel partners), and see
 * their own booking history. Every route here sits behind the 'auth:customer'
 * guard (see routes/web.php), so $guest is always the signed-in Guest.
 */
class BookingController extends Controller
{
    public function index(Request $request): View
    {
        /** @var Guest $guest */
        $guest = Auth::guard('customer')->user();

        $data = $request->validate([
            'check_in_date' => ['nullable', 'date'],
            'check_out_date' => ['nullable', 'date', 'after:check_in_date'],
        ]);

        $availableRooms = null;

        if (! empty($data['check_in_date']) && ! empty($data['check_out_date'])) {
            $checkIn = Carbon::parse($data['check_in_date']);
            $checkOut = Carbon::parse($data['check_out_date']);

            $availableRooms = Room::query()
                ->where('is_active', true)
                ->whereDoesntHave('bookings', function ($query) use ($checkIn, $checkOut) {
                    $query->where('status', '!=', BookingStatus::Cancelled)
                        ->where('check_in_date', '<', $checkOut)
                        ->where('check_out_date', '>', $checkIn);
                })
                ->with('roomType')
                ->get()
                ->map(fn (Room $room) => [
                    'room' => $room,
                    'nights' => max(1, $checkIn->diffInDays($checkOut)),
                    'price_for_stay' => $room->roomType->effectivePriceForRange($checkIn, $checkOut),
                ]);
        }

        return view('customer-app-bookings', [
            'guest' => $guest,
            'checkInDate' => $data['check_in_date'] ?? null,
            'checkOutDate' => $data['check_out_date'] ?? null,
            'availableRooms' => $availableRooms,
            'bookings' => $guest->bookings()
                ->with(['room.roomType'])
                ->orderByDesc('check_in_date')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var Guest $guest */
        $guest = Auth::guard('customer')->user();

        $data = $request->validate([
            'room_id' => ['required', 'integer'],
            'check_in_date' => ['required', 'date'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'adults_count' => ['sometimes', 'integer', 'min:1'],
            'children_count' => ['sometimes', 'integer', 'min:0'],
        ]);

        try {
            DB::transaction(function () use ($data, $guest) {
                $room = Room::query()->lockForUpdate()->findOrFail($data['room_id']);

                $checkIn = Carbon::parse($data['check_in_date']);
                $checkOut = Carbon::parse($data['check_out_date']);

                Booking::assertRoomAvailable($room, $checkIn, $checkOut);

                Booking::create([
                    'room_id' => $room->id,
                    'guest_id' => $guest->id,
                    'check_in_date' => $checkIn,
                    'check_out_date' => $checkOut,
                    'adults_count' => $data['adults_count'] ?? 1,
                    'children_count' => $data['children_count'] ?? 0,
                    'status' => BookingStatus::Confirmed,
                    'source' => BookingSource::Online,
                ]);
            });
        } catch (RoomNotAvailableException $exception) {
            return back()->withErrors(['room_id' => $exception->getMessage()]);
        }

        return redirect()->route('customer-app.bookings')->with('success', 'رزرو شما با موفقیت ثبت شد.');
    }
}
