<?php

namespace App\Filament\Resources\Bookings\Tables;

use App\Enums\BookingStatus;
use App\Exceptions\InsufficientWalletBalanceException;
use App\Filament\Support\TableExports;
use App\Models\Booking;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Number;
use InvalidArgumentException;

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
            ->headerActions([
                TableExports::excel('رزروها', static::exportColumns()),
                TableExports::pdf('گزارش رزروها', static::exportColumns()),
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
                Action::make('payFromWallet')
                    ->label('پرداخت از کیف پول')
                    ->icon(Heroicon::OutlinedWallet)
                    ->color('success')
                    ->visible(fn (Booking $record) => in_array($record->status, [BookingStatus::Pending, BookingStatus::Confirmed, BookingStatus::CheckedIn], true)
                        && (int) $record->guest?->wallet_balance > 0
                        && $record->walletPayableAmount() > 0)
                    ->modalDescription(fn (Booking $record) => 'موجودی کیف پول: '.Number::toPersian(number_format((int) $record->guest->wallet_balance)).' تومان - مانده‌ی قابل پرداخت صورت‌حساب: '.Number::toPersian(number_format($record->walletPayableAmount())).' تومان. مبلغ پرداخت‌شده از کیف پول کش‌بک نمی‌گیرد.')
                    ->fillForm(fn (Booking $record) => [
                        'amount' => min((int) $record->guest->wallet_balance, $record->walletPayableAmount()),
                    ])
                    ->schema([
                        TextInput::make('amount')
                            ->label('مبلغ (تومان)')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                    ])
                    ->action(function (Booking $record, array $data) {
                        try {
                            $record->payFromWallet((int) $data['amount']);
                        } catch (InsufficientWalletBalanceException $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();

                            return;
                        } catch (InvalidArgumentException $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('پرداخت از کیف پول ثبت شد')->success()->send();
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

    /**
     * @return array<string, \Closure(Booking): mixed>
     */
    private static function exportColumns(): array
    {
        return [
            'کد رزرو' => fn (Booking $booking) => $booking->reference_code,
            'اتاق/ویلا' => fn (Booking $booking) => $booking->room?->name,
            'مهمان' => fn (Booking $booking) => $booking->guest?->name,
            'ورود' => fn (Booking $booking) => $booking->check_in_date?->toJalali('Y/m/d'),
            'خروج' => fn (Booking $booking) => $booking->check_out_date?->toJalali('Y/m/d'),
            'وضعیت' => fn (Booking $booking) => $booking->status->getLabel(),
            'منبع' => fn (Booking $booking) => $booking->source?->getLabel(),
        ];
    }
}
