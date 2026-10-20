<?php

namespace App\Filament\Resources\Bookings\Pages;

use App\Enums\BookingStatus;
use App\Filament\Resources\Bookings\BookingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListBookings extends ListRecords
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * Quick views for the questions the desk asks all day. «همه» stays first
     * so the list opens exactly as before.
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('همه'),
            'arrivals_today' => Tab::make('ورود امروز')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereIn('status', [BookingStatus::Pending, BookingStatus::Confirmed])
                    ->whereDate('check_in_date', today())),
            'in_house' => Tab::make('مقیم')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', BookingStatus::CheckedIn)),
            'upcoming' => Tab::make('آینده')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->whereIn('status', [BookingStatus::Pending, BookingStatus::Confirmed])
                    ->whereDate('check_in_date', '>', today())),
            'pending' => Tab::make('در انتظار تأیید')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', BookingStatus::Pending)),
            'cancelled' => Tab::make('لغو شده')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', BookingStatus::Cancelled)),
        ];
    }
}
