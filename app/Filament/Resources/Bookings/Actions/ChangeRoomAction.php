<?php

namespace App\Filament\Resources\Bookings\Actions;

use App\Enums\BookingStatus;
use App\Exceptions\RoomNotAvailableException;
use App\Models\Booking;
use App\Models\Room;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Number;
use InvalidArgumentException;

/**
 * «تغییر اتاق»: moves the booking to another room that is free for all its
 * nights, optionally putting the price difference of a different room type on
 * the folio (leave it off for a free upgrade).
 */
class ChangeRoomAction
{
    public static function make(): Action
    {
        return Action::make('changeRoom')
            ->label('تغییر اتاق')
            ->icon(Heroicon::OutlinedArrowsRightLeft)
            ->color('info')
            ->visible(fn (Booking $record) => in_array($record->status, [BookingStatus::Pending, BookingStatus::Confirmed, BookingStatus::CheckedIn], true))
            ->modalHeading('تغییر اتاق')
            ->modalDescription(fn (Booking $record) => 'اتاق فعلی: '.$record->room->name.'. فقط اتاق‌هایی که برای تمام شب‌های این رزرو آزادند نشان داده می‌شوند.')
            ->fillForm(['charge_difference' => true])
            ->schema([
                Select::make('room_id')
                    ->label('اتاق تازه')
                    ->options(fn (Booking $record) => Room::query()
                        ->freeBetween($record->check_in_date, $record->check_out_date, $record->id)
                        ->whereKeyNot($record->room_id)
                        ->with('roomType')
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (Room $room) => [$room->id => $room->name.' - '.$room->roomType->name])
                        ->all())
                    ->searchable()
                    ->required()
                    ->live(),
                Placeholder::make('difference')
                    ->label('مابه‌التفاوت قیمت')
                    ->content(function (Get $get, Booking $record) {
                        $room = filled($get('room_id')) ? Room::query()->with('roomType')->find($get('room_id')) : null;

                        if (! $room) {
                            return '—';
                        }

                        $difference = $record->roomChangeDifference($room);

                        return match (true) {
                            $difference > 0 => Number::toPersian(number_format($difference)).' تومان اضافه',
                            $difference < 0 => Number::toPersian(number_format(abs($difference))).' تومان کم',
                            default => 'بدون تفاوت',
                        };
                    }),
                Toggle::make('charge_difference')
                    ->label('تفاوت قیمت در صورت‌حساب بیاید')
                    ->helperText('خاموش = ارتقای رایگان؛ مهمان همان مبلغ توافق‌شده را می‌پردازد.'),
            ])
            ->action(function (Booking $record, array $data): void {
                try {
                    $record->changeRoom(Room::query()->findOrFail($data['room_id']), (bool) $data['charge_difference'], auth()->id());
                } catch (RoomNotAvailableException|InvalidArgumentException $exception) {
                    Notification::make()->title('اتاق عوض نشد')->body($exception->getMessage())->danger()->persistent()->send();

                    return;
                }

                Notification::make()->title('اتاق عوض شد')->body('اتاق تازه: '.$record->room->name)->success()->send();
            });
    }
}
