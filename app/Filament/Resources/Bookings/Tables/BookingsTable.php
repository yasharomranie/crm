<?php

namespace App\Filament\Resources\Bookings\Tables;

use App\Enums\BookingStatus;
use App\Exceptions\InsufficientWalletBalanceException;
use App\Filament\Resources\Bookings\Actions\ChangeRoomAction;
use App\Filament\Resources\Bookings\Actions\ExtendStayAction;
use App\Filament\Resources\Bookings\Actions\PaymentLinkAction;
use App\Filament\Resources\Bookings\Actions\RecordPaymentAction;
use App\Filament\Resources\Bookings\Actions\SendBookingSmsAction;
use App\Filament\Support\TableExports;
use App\Models\Booking;
use App\Models\CancellationPolicy;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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
                TextColumn::make('hold_expires_at')->label('نگه‌داری تا')->jalaliDateTime('Y/m/d H:i')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('folio_total')
                    ->label('صورت‌حساب')
                    ->state(fn (Booking $record) => $record->totalAmount())
                    ->numeric()
                    ->suffix(' تومان')
                    ->toggleable(),
                TextColumn::make('folio_balance')
                    ->label('مانده')
                    ->state(fn (Booking $record) => $record->balanceDue())
                    ->numeric()
                    ->suffix(' تومان')
                    ->color(fn (Booking $record) => $record->balanceDue() > 0 ? 'danger' : 'success'),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['charges', 'payments']))
            ->defaultSort('check_in_date', 'desc')
            ->filters([
                SelectFilter::make('status')->label('وضعیت')->options(BookingStatus::class),
                Filter::make('has_balance')
                    ->label('فقط رزروهای دارای مانده')
                    ->query(fn (Builder $query) => $query
                        ->whereRaw('(case when bookings.status = ? then bookings.cancellation_fee'
                            .' else (select coalesce(sum(quantity * unit_amount), 0) from booking_charges where booking_charges.booking_id = bookings.id) end)'
                            .' - coalesce(bookings.wallet_paid_amount, 0)'
                            .' - (select coalesce(sum(case when kind = ? then -amount else amount end), 0) from booking_payments where booking_payments.booking_id = bookings.id) > 0', [BookingStatus::Cancelled->value, 'refund'])),
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
                    ->modalDescription(fn (Booking $record) => 'اتاق به «نیازمند نظافت» تغییر می‌کند و یک تسک نظافت خودکار برای دپارتمان مربوطه ساخته می‌شود.'
                        .($record->balanceDue() > 0 ? ' توجه: '.Number::toPersian(number_format($record->balanceDue())).' تومان از صورت‌حساب هنوز پرداخت نشده است.' : ''))
                    ->action(function (Booking $record) {
                        $record->checkOut();

                        Notification::make()
                            ->title('چک‌اوت ثبت شد و تسک نظافت ساخته شد')
                            ->success()
                            ->send();
                    }),
                ExtendStayAction::make(),
                ChangeRoomAction::make(),
                RecordPaymentAction::make(),
                SendBookingSmsAction::make(),
                PaymentLinkAction::make(),
                Action::make('printInvoice')
                    ->label('چاپ صورت‌حساب')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->color('gray')
                    ->url(fn (Booking $record) => route('bookings.invoice', $record))
                    ->openUrlInNewTab(),
                Action::make('cancelBooking')
                    ->label('لغو رزرو')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (Booking $record) => in_array($record->status, [BookingStatus::Pending, BookingStatus::Confirmed], true))
                    ->modalHeading('لغو رزرو')
                    ->modalDescription(fn (Booking $record) => static::cancellationSummary($record, false))
                    ->schema([
                        Textarea::make('reason')->label('دلیل (اختیاری)')->maxLength(255),
                        Toggle::make('waive_fee')->label('جریمه‌ی لغو بخشیده شود'),
                    ])
                    ->action(fn (Booking $record, array $data) => static::runCancellation($record, $data, false)),
                Action::make('markNoShow')
                    ->label('عدم حضور')
                    ->icon(Heroicon::OutlinedUserMinus)
                    ->color('gray')
                    ->visible(fn (Booking $record) => in_array($record->status, [BookingStatus::Pending, BookingStatus::Confirmed], true)
                        && $record->check_in_date->lte(now()->endOfDay()))
                    ->modalHeading('ثبت عدم حضور مهمان')
                    ->modalDescription(fn (Booking $record) => static::cancellationSummary($record, true))
                    ->schema([
                        Toggle::make('waive_fee')->label('جریمه‌ی عدم حضور بخشیده شود'),
                    ])
                    ->action(fn (Booking $record, array $data) => static::runCancellation($record, $data, true)),
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

    /**
     * What ending the booking now would cost, in words, from the terms the
     * booking kept when it was made.
     */
    private static function cancellationSummary(Booking $booking, bool $noShow): string
    {
        $terms = $booking->cancellation_terms;
        $percent = $booking->cancellationPenaltyPercent($noShow);
        $fee = $booking->cancellationFeeFor($noShow);

        return ($terms ? 'شرایط لغو این رزرو: '.CancellationPolicy::describe($terms).'. ' : 'برای این رزرو سیاست لغو ثبت نشده؛ جریمه‌ای اعمال نمی‌شود. ')
            .'اگر همین حالا ثبت شود: جریمه '.Number::toPersian((string) $percent).'٪ = '.Number::toPersian(number_format($fee)).' تومان. '
            .'پرداخت‌شده تاکنون: '.Number::toPersian(number_format($booking->paidAmount())).' تومان.';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function runCancellation(Booking $booking, array $data, bool $noShow): void
    {
        $walletBefore = (int) $booking->wallet_paid_amount;

        try {
            $booking->cancel($data['reason'] ?? null, $noShow, (bool) ($data['waive_fee'] ?? false));
        } catch (InvalidArgumentException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        }

        $booking->refresh();
        $parts = [
            ($noShow ? 'عدم حضور ثبت شد' : 'رزرو لغو شد').'. جریمه: '.Number::toPersian(number_format((int) $booking->cancellation_fee)).' تومان.',
        ];

        $returned = $walletBefore - (int) $booking->wallet_paid_amount;

        if ($returned > 0) {
            $parts[] = Number::toPersian(number_format($returned)).' تومان به کیف پول مهمان برگشت.';
        }

        if ($booking->refundableAmount() > 0) {
            $parts[] = 'قابل استرداد نقدی: '.Number::toPersian(number_format($booking->refundableAmount())).' تومان (با «ثبت پرداخت ← استرداد» ثبت کنید).';
        }

        if ($booking->balanceDue() > 0) {
            $parts[] = 'مانده‌ی جریمه: '.Number::toPersian(number_format($booking->balanceDue())).' تومان.';
        }

        Notification::make()->title(implode(' ', $parts))->success()->send();
    }
}
