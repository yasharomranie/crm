<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Enums\BookingStatus;
use App\Exceptions\RoomNotAvailableException;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Models\Room;
use Carbon\Carbon;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditBooking extends EditRecord
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * Overbooking prevention on edit, mirroring
     * CreateBooking::handleRecordCreation(): changing a booking's room or
     * dates must not silently create a conflict with another booking. A
     * booking being cancelled is exempt, since it no longer occupies the
     * room.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return DB::transaction(function () use ($record, $data) {
                $room = Room::query()->lockForUpdate()->findOrFail($data['room_id']);

                if ($data['status'] !== BookingStatus::Cancelled->value) {
                    Booking::assertRoomAvailable(
                        $room,
                        Carbon::parse($data['check_in_date']),
                        Carbon::parse($data['check_out_date']),
                        $record->getKey(),
                    );
                }

                $record->update($data);

                return $record;
            });
        } catch (RoomNotAvailableException $exception) {
            Notification::make()
                ->title('امکان ذخیره تغییرات وجود ندارد')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            $this->halt();
        }
    }
}
