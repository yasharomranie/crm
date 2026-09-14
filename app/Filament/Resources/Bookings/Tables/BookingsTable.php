<?php

namespace App\Filament\Resources\Bookings\Tables;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference_code')->label('کد رزرو')->fontFamily('mono')->searchable(),
                TextColumn::make('room.name')->label('اتاق/ویلا'),
                TextColumn::make('guest.name')->label('مهمان')->searchable(),
                TextColumn::make('check_in_date')->label('ورود')->jalaliDate('Y/m/d'),
                TextColumn::make('check_out_date')->label('خروج')->jalaliDate('Y/m/d'),
                TextColumn::make('status')->label('وضعیت')->badge(),
                TextColumn::make('source')->label('منبع'),
            ])
            ->defaultSort('check_in_date', 'desc')
            ->filters([
                SelectFilter::make('status')->label('وضعیت')->options(BookingStatus::class),
            ])
            ->recordActions([
                Action::make('checkIn')
                    ->label('چک‌این')
                    ->icon(Heroicon::OutlinedArrowRightOnRectangle)
                    ->color('info')
                    ->visible(fn (Booking $record) => in_array($record->status, [BookingStatus::Pending, BookingStatus::Confirmed]))
                    ->requiresConfirmation()
                    ->action(function (Booking $record) {
                        $record->checkIn();

                        Notification::make()
                            ->title('چک‌این با موفقیت ثبت شد')
                            ->success()
                            ->send();
                    }),
                Action::make('checkOut')
                    ->label('چک‌اوت')
                    ->icon(Heroicon::OutlinedArrowLeftOnRectangle)
                    ->color('warning')
                    ->visible(fn (Booking $record) => $record->status === BookingStatus::CheckedIn)
                    ->requiresConfirmation()
                    ->modalDescription('اتاق به «نیازمند نظافت» تغییر می‌کند و یک تسک نظافت خودکار برای دپارتمان مربوطه ساخته می‌شود.')
                    ->action(function (Booking $record) {
                        $record->checkOut();

                        Notification::make()
                            ->title('چک‌اوت ثبت شد و تسک نظافت ساخته شد')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
