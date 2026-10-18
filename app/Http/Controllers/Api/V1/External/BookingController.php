<?php

namespace App\Http\Controllers\Api\V1\External;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Exceptions\RoomNotAvailableException;
use App\Http\Controllers\Api\V1\External\Concerns\RunsIdempotently;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ExternalApiClient;
use App\Models\Guest;
use App\Models\IdempotencyKey;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    use RunsIdempotently;

    public function store(Request $request): JsonResponse
    {
        /** @var ExternalApiClient $client */
        $client = $request->attributes->get('external_api_client');

        $idempotencyKey = $request->header('Idempotency-Key');

        if (! $idempotencyKey) {
            return $this->error('INVALID_REQUEST', 'هدر Idempotency-Key الزامی است.', 400);
        }

        $data = $request->validate([
            'room_id' => ['required', 'integer'],
            'check_in_date' => ['required', 'date'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'adults_count' => ['sometimes', 'integer', 'min:1'],
            'children_count' => ['sometimes', 'integer', 'min:0'],
            'sales_channel_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'guest' => ['required', 'array'],
            'guest.name' => ['required', 'string', 'max:255'],
            'guest.mobile' => ['required', 'string', 'max:32'],
            'guest.national_code' => ['nullable', 'string', 'max:20'],
            'guest.email' => ['nullable', 'email', 'max:255'],
        ]);

        $requestHash = hash('sha256', json_encode(['client' => $client->id, ...$data]));

        if ($replay = $this->replayIfSeen($client, $idempotencyKey, $requestHash)) {
            return $replay;
        }

        if (! $client->hasPermission('booking.create')) {
            return $this->error('FORBIDDEN', 'این اکانت مجاز به ثبت رزرو نیست.', 403);
        }

        return $this->idempotently($client, $idempotencyKey, $requestHash, fn () => $this->createBooking($client, $data));
    }

    public function show(Request $request, string $referenceCode): JsonResponse
    {
        /** @var ExternalApiClient $client */
        $client = $request->attributes->get('external_api_client');

        if (! $client->hasPermission('booking.read')) {
            return $this->error('FORBIDDEN', 'این اکانت مجاز به مشاهده‌ی رزرو نیست.', 403);
        }

        $booking = Booking::with(['room.roomType', 'guest'])
            ->where('reference_code', $referenceCode)
            ->first();

        // Same ownership rule as tickets: a booking created by a
        // different client (or not through this API at all - walk-in,
        // phone, admin panel) is never readable here.
        if (! $booking || $booking->external_api_client_id !== $client->id) {
            return $this->error('NOT_FOUND', 'رزروی با این کد یافت نشد.', 404);
        }

        return response()->json($this->transformBooking($booking));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: int, 1: array<string, mixed>}
     */
    private function createBooking(ExternalApiClient $client, array $data): array
    {
        try {
            $booking = DB::transaction(function () use ($client, $data) {
                $room = Room::query()->lockForUpdate()->findOrFail($data['room_id']);

                $checkIn = Carbon::parse($data['check_in_date']);
                $checkOut = Carbon::parse($data['check_out_date']);

                Booking::assertRoomAvailable($room, $checkIn, $checkOut);

                $guest = Guest::firstOrCreate(
                    ['mobile' => $data['guest']['mobile']],
                    [
                        'name' => $data['guest']['name'],
                        'national_code' => $data['guest']['national_code'] ?? null,
                        'email' => $data['guest']['email'] ?? null,
                    ],
                );

                return Booking::create([
                    'room_id' => $room->id,
                    'guest_id' => $guest->id,
                    'check_in_date' => $checkIn,
                    'check_out_date' => $checkOut,
                    'adults_count' => $data['adults_count'] ?? 1,
                    'children_count' => $data['children_count'] ?? 0,
                    'status' => BookingStatus::Confirmed,
                    'sales_channel_id' => $data['sales_channel_id'] ?? null,
                    'external_api_client_id' => $client->id,
                    'source' => ($data['sales_channel_id'] ?? null) ? BookingSource::Ota : BookingSource::Online,
                    'notes' => $data['notes'] ?? null,
                ]);
            });

            return [201, $this->transformBooking($booking->load(['room.roomType', 'guest']))];
        } catch (RoomNotAvailableException $exception) {
            return [409, ['error' => ['code' => 'CONFLICT', 'message' => $exception->getMessage()]]];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function transformBooking(Booking $booking): array
    {
        return [
            'booking_id' => $booking->id,
            'reference_code' => $booking->reference_code,
            'status' => $booking->status->value,
            'room' => [
                'id' => $booking->room->id,
                'name' => $booking->room->name,
                'room_type' => $booking->room->roomType->name,
            ],
            'guest' => [
                'name' => $booking->guest->name,
                'mobile' => $booking->guest->mobile,
            ],
            'check_in_date' => $booking->check_in_date->toDateString(),
            'check_out_date' => $booking->check_out_date->toDateString(),
            'nights' => $booking->nights(),
            'total_amount' => $booking->totalAmount(),
        ];
    }

    private function replayIfSeen(ExternalApiClient $client, string $key, string $requestHash): ?JsonResponse
    {
        $existing = IdempotencyKey::where('external_api_client_id', $client->id)->where('key', $key)->first();

        if (! $existing) {
            return null;
        }

        if ($existing->request_hash !== $requestHash) {
            return $this->error('CONFLICT', 'این Idempotency-Key قبلاً با محتوای متفاوتی استفاده شده است.', 409);
        }

        return response()->json($existing->response_body, $existing->response_status);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
