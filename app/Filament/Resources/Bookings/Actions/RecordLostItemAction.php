<?php

namespace App\Filament\Resources\Bookings\Actions;

use App\Domain\Hospitality\LostItemService;
use App\Models\Booking;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * «ثبت شیء جامانده»: logs something found in the room of a stay, tied to the
 * booking's guest so reception can text them from the lost-items list.
 */
class RecordLostItemAction
{
    public static function make(): Action
    {
        return Action::make('recordLostItem')
            ->label('ثبت شیء جامانده')
            ->icon(Heroicon::OutlinedArchiveBox)
            ->color('gray')
            ->modalHeading('ثبت شیء جامانده')
            ->schema([
                TextInput::make('description')->label('شیء')->required()->maxLength(255),
                TextInput::make('location')->label('محل پیداشدن')->maxLength(255),
            ])
            ->action(function (Booking $record, array $data): void {
                app(LostItemService::class)->recordForBooking($record, $data['description'], $data['location'] ?? null, auth()->id());

                Notification::make()->title('شیء جامانده ثبت شد')->success()->send();
            });
    }
}
