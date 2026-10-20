<?php

namespace App\Filament\Resources\BookingGroups\RelationManagers;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Number;

/**
 * The rooms of a group. Read-only: each room is an ordinary booking and is
 * changed from the bookings list (check-in, extending, moving, cancelling).
 */
class BookingsRelationManager extends RelationManager
{
    protected static string $relationship = 'bookings';

    protected static ?string $title = 'اتاق‌های گروه';

    protected static ?string $modelLabel = 'رزرو';

    protected static ?string $pluralModelLabel = 'رزروها';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reference_code')
            ->modifyQueryUsing(fn ($query) => $query->with(['room', 'charges', 'payments']))
            ->columns([
                TextColumn::make('reference_code')->label('کد رزرو')->fontFamily('mono'),
                TextColumn::make('room.name')->label('اتاق/ویلا'),
                TextColumn::make('check_in_date')->label('ورود')->jalaliDate('Y/m/d'),
                TextColumn::make('check_out_date')->label('خروج')->jalaliDate('Y/m/d'),
                TextColumn::make('status')->label('وضعیت')->badge(),
                TextColumn::make('adults_count')->label('بزرگسال')->numeric(),
                TextColumn::make('total')->label('صورت‌حساب')->state(fn (Booking $record) => Number::toPersian(number_format($record->amountOwed())))->suffix(' تومان'),
                TextColumn::make('balance')->label('مانده')
                    ->state(fn (Booking $record) => Number::toPersian(number_format($record->balanceDue())))
                    ->suffix(' تومان')
                    ->color(fn (Booking $record) => $record->balanceDue() > 0 ? 'warning' : 'success'),
            ])
            ->defaultSort('check_in_date')
            ->recordActions([
                Action::make('open')
                    ->label('باز کردن رزرو')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Booking $record) => BookingResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
