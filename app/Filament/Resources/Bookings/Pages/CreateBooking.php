<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Exceptions\RoomNotAvailableException;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Models\BookingPromoCode;
use App\Models\Room;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateBooking extends CreateRecord
{
    protected static string $resource = BookingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }

    /**
     * Overbooking prevention, concurrency-safe: two admins submitting the
     * same room/dates at the same instant must not both succeed. The
     * model's own `creating` hook (Booking::assertRoomAvailable()) already
     * catches the common case, but without a row lock two concurrent
     * transactions could both pass that check before either commits. This
     * wraps creation in a transaction with `lockForUpdate()` on the room
     * row, serializing concurrent attempts so the second one re-checks
     * against the first one's now-committed booking.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $promoCode = $data['promo_code'] ?? null;
        unset($data['promo_code']);

        try {
            return DB::transaction(function () use ($data, $promoCode) {
                $room = Room::query()->lockForUpdate()->findOrFail($data['room_id']);

                Booking::assertRoomAvailable(
                    $room,
                    Carbon::parse($data['check_in_date']),
                    Carbon::parse($data['check_out_date']),
                );

                if (filled($promoCode)) {
                    $data['promo_code_id'] = BookingPromoCode::resolve(
                        $promoCode,
                        max(1, (int) Carbon::parse($data['check_in_date'])->startOfDay()->diffInDays(Carbon::parse($data['check_out_date'])->startOfDay())),
                    )->id;
                }

                return Booking::create($data);
            });
        } catch (RoomNotAvailableException $exception) {
            Notification::make()
                ->title('امکان ثبت رزرو وجود ندارد')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            $this->halt();
        }
    }
}
