<?php

namespace App\Http\Controllers\Api\V1\External;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\ExternalApiClient;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The same overlap rule as Booking::assertRoomAvailable() (a room is free
 * for a range when no non-cancelled booking on it overlaps that range) -
 * expressed as a query instead of a per-room check, so a channel can list
 * every free room for a date range in one call before booking one of them.
 */
class RoomAvailabilityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var ExternalApiClient $client */
        $client = $request->attributes->get('external_api_client');

        if (! $client->hasPermission('room.read')) {
            return $this->error('FORBIDDEN', 'این اکانت مجاز به مشاهده‌ی اتاق‌ها نیست.', 403);
        }

        $data = $request->validate([
            'check_in' => ['required', 'date'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'room_type_id' => ['nullable', 'integer'],
        ]);

        $rooms = Room::query()
            ->where('is_active', true)
            ->when($data['room_type_id'] ?? null, fn ($query, $roomTypeId) => $query->where('room_type_id', $roomTypeId))
            ->whereDoesntHave('bookings', function ($query) use ($data) {
                $query->where('status', '!=', BookingStatus::Cancelled)
                    ->where('check_in_date', '<', $data['check_out'])
                    ->where('check_out_date', '>', $data['check_in']);
            })
            ->with('roomType')
            ->get();

        return response()->json([
            'data' => $rooms->map(fn (Room $room) => [
                'room_id' => $room->id,
                'name' => $room->name,
                'room_type' => [
                    'id' => $room->roomType->id,
                    'name' => $room->roomType->name,
                    'capacity' => $room->roomType->capacity,
                ],
                'price_for_stay' => $room->roomType->effectivePriceForRange(
                    Carbon::parse($data['check_in']),
                    Carbon::parse($data['check_out']),
                ),
            ]),
        ]);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
