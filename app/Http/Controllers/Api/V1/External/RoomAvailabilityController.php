<?php

namespace App\Http\Controllers\Api\V1\External;

use App\Domain\Hospitality\StayPricing;
use App\Enums\BookingStatus;
use App\Exceptions\InvalidPromoCodeException;
use App\Http\Controllers\Controller;
use App\Models\BookingPromoCode;
use App\Models\ChannelAllotment;
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
            'sales_channel_id' => ['nullable', 'integer'],
            'adults' => ['nullable', 'integer', 'min:1'],
            'children' => ['nullable', 'integer', 'min:0'],
            'promo_code' => ['nullable', 'string', 'max:40'],
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
            ->get()
            // A channel that has used up its allotment of a room type cannot
            // book it, so that type is not offered to it (see ChannelAllotment).
            ->when($data['sales_channel_id'] ?? null, fn ($rooms, $channelId) => $rooms->filter(
                fn (Room $room) => ChannelAllotment::hasRoomFor((int) $channelId, $room->room_type_id, Carbon::parse($data['check_in']), Carbon::parse($data['check_out'])),
            )->values());

        $checkIn = Carbon::parse($data['check_in']);
        $checkOut = Carbon::parse($data['check_out']);
        $nights = max(1, (int) $checkIn->copy()->startOfDay()->diffInDays($checkOut->copy()->startOfDay()));

        try {
            $promo = filled($data['promo_code'] ?? null) ? BookingPromoCode::resolve($data['promo_code'], $nights) : null;
        } catch (InvalidPromoCodeException $exception) {
            return $this->error('INVALID_PROMO_CODE', $exception->getMessage(), 422);
        }

        $pricing = app(StayPricing::class);

        // A stay shorter than the room type (or a price rule) demands cannot
        // be booked, so the room is not offered.
        $rooms = $rooms->filter(fn (Room $room) => $nights >= $room->roomType->minimumNightsFor($checkIn, $checkOut))->values();

        return response()->json([
            'data' => $rooms->map(function (Room $room) use ($pricing, $checkIn, $checkOut, $data, $promo) {
                $quote = $pricing->quote($room->roomType, $checkIn, $checkOut, (int) ($data['adults'] ?? 1), (int) ($data['children'] ?? 0), $promo);

                return [
                    'room_id' => $room->id,
                    'name' => $room->name,
                    'room_type' => [
                        'id' => $room->roomType->id,
                        'name' => $room->roomType->name,
                        'capacity' => $room->roomType->capacity,
                    ],
                    'price_for_stay' => $quote['room_total'],
                    'quote' => [
                        'nights' => $quote['nights'],
                        'room_total' => $quote['room_total'],
                        'extra_guests_total' => $quote['extra_guests_total'],
                        'stay_discount' => $quote['stay_discount'],
                        'promo_discount' => $quote['promo_discount'],
                        'total' => $quote['total'],
                    ],
                ];
            }),
        ]);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
