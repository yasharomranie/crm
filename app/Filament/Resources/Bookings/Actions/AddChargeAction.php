<?php

namespace App\Filament\Resources\Bookings\Actions;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\ChargeItem;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Number;

/**
 * «افزودن خدمت»: puts a ready-made extra (breakfast, laundry...) from the
 * services list on a stay's folio. Used from the bookings list and from the
 * folio panel of a booking.
 */
class AddChargeAction
{
    public static function make(): Action
    {
        return Action::make('addCatalogCharge')
            ->label('افزودن خدمت')
            ->icon(Heroicon::OutlinedShoppingBag)
            ->color('gray')
            ->visible(fn (Booking $record) => in_array($record->status, [BookingStatus::Pending, BookingStatus::Confirmed, BookingStatus::CheckedIn], true)
                && ChargeItem::query()->where('is_active', true)->exists())
            ->modalHeading('افزودن خدمت به صورت‌حساب')
            ->fillForm(['quantity' => 1])
            ->schema([
                Select::make('charge_item_id')
                    ->label('خدمت')
                    ->options(fn () => ChargeItem::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get()
                        ->mapWithKeys(fn (ChargeItem $item) => [$item->id => $item->name.' - '.Number::toPersian(number_format($item->unit_amount)).' تومان'])
                        ->all())
                    ->searchable()
                    ->required(),
                TextInput::make('quantity')->label('تعداد')->numeric()->minValue(1)->default(1)->required(),
            ])
            ->action(function (Booking $record, array $data): void {
                $item = ChargeItem::query()->where('is_active', true)->find($data['charge_item_id']);

                if (! $item) {
                    Notification::make()->title('این خدمت دیگر فعال نیست')->danger()->send();

                    return;
                }

                $item->addTo($record, (int) $data['quantity']);

                Notification::make()->title('به صورت‌حساب اضافه شد')->body($item->name.' × '.Number::toPersian((int) $data['quantity']))->success()->send();
            });
    }
}
