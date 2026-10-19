<?php

namespace App\Filament\Resources\Bookings\Actions;

use App\Enums\BookingStatus;
use App\Exceptions\RoomNotAvailableException;
use App\Models\Booking;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;
use InvalidArgumentException;

/**
 * «تمدید اقامت»: moves the check-out date later and adds the extra nights to
 * the folio, if the room is free for them.
 */
class ExtendStayAction
{
    public static function make(): Action
    {
        return Action::make('extendStay')
            ->label('تمدید اقامت')
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color('info')
            ->visible(fn (Booking $record) => in_array($record->status, [BookingStatus::Pending, BookingStatus::Confirmed, BookingStatus::CheckedIn], true))
            ->modalHeading('تمدید اقامت')
            ->modalDescription(fn (Booking $record) => 'خروج فعلی: '.$record->check_out_date->toJalali('Y/m/d').'. اتاق باید برای شب‌های اضافه آزاد باشد.')
            ->fillForm(fn (Booking $record) => ['new_check_out' => $record->check_out_date->copy()->addDay()->toDateString()])
            ->schema([
                DatePicker::make('new_check_out')
                    ->jalali()
                    ->displayFormat('Y/m/d')
                    ->label('تاریخ خروج تازه')
                    ->required()
                    ->live(),
                Placeholder::make('extension_price')
                    ->label('هزینه‌ی شب‌های اضافه')
                    ->content(function (Get $get, Booking $record) {
                        try {
                            $date = Carbon::parse($get('new_check_out'));

                            return $date->gt($record->check_out_date)
                                ? Number::toPersian(number_format($record->extensionPrice($date))).' تومان'
                                : 'تاریخ باید بعد از خروج فعلی باشد.';
                        } catch (\Throwable) {
                            return '—';
                        }
                    }),
            ])
            ->action(function (Booking $record, array $data): void {
                try {
                    $record->extendStay(Carbon::parse($data['new_check_out']), auth()->id());
                } catch (RoomNotAvailableException|InvalidArgumentException $exception) {
                    Notification::make()->title('تمدید انجام نشد')->body($exception->getMessage())->danger()->persistent()->send();

                    return;
                }

                Notification::make()->title('اقامت تمدید شد')->body('خروج تازه: '.$record->check_out_date->toJalali('Y/m/d'))->success()->send();
            });
    }
}
