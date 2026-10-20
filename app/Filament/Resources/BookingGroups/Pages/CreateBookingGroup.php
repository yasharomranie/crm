<?php

namespace App\Filament\Resources\BookingGroups\Pages;

use App\Exceptions\RoomNotAvailableException;
use App\Filament\Resources\BookingGroups\BookingGroupResource;
use App\Models\Booking;
use App\Models\BookingGroup;
use App\Models\Room;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CreateBookingGroup extends CreateRecord
{
    protected static string $resource = BookingGroupResource::class;

    /**
     * All rooms are booked in one transaction: if any room is taken (or a
     * channel allotment is full) nothing at all is saved and the message says which.
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return DB::transaction(function () use ($data) {
                $group = BookingGroup::create([
                    'guest_id' => $data['guest_id'],
                    'name' => $data['name'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'created_by' => auth()->id(),
                ]);

                $checkIn = Carbon::parse($data['check_in_date']);
                $checkOut = Carbon::parse($data['check_out_date']);

                foreach ($data['rooms'] as $line) {
                    $room = Room::query()->lockForUpdate()->findOrFail($line['room_id']);

                    Booking::create([
                        'room_id' => $room->id,
                        'guest_id' => $group->guest_id,
                        'booking_group_id' => $group->id,
                        'check_in_date' => $checkIn,
                        'check_out_date' => $checkOut,
                        'adults_count' => (int) $line['adults_count'],
                        'children_count' => (int) ($line['children_count'] ?? 0),
                        'status' => $data['status'],
                        'source' => $data['source'],
                        'sales_channel_id' => $data['sales_channel_id'] ?? null,
                        'created_by' => auth()->id(),
                    ]);
                }

                return $group;
            });
        } catch (RoomNotAvailableException $exception) {
            Notification::make()->title('رزرو گروهی ثبت نشد')->body($exception->getMessage())->danger()->persistent()->send();

            $this->halt();
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
