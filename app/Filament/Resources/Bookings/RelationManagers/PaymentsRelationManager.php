<?php

namespace App\Filament\Resources\Bookings\RelationManagers;

use App\Enums\BookingPaymentKind;
use App\Filament\Resources\Bookings\Actions\RecordPaymentAction;
use App\Models\BookingPayment;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Number;

/**
 * The money ledger of a booking. Read-only on purpose: lines are added with
 * «ثبت پرداخت» and a mistake is corrected with a refund, never by editing or
 * deleting a line.
 */
class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'پرداخت‌ها';

    protected static ?string $modelLabel = 'پرداخت';

    protected static ?string $pluralModelLabel = 'پرداخت‌ها';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reference')
            ->columns([
                TextColumn::make('paid_at')->label('تاریخ')->jalaliDate('Y/m/d'),
                TextColumn::make('kind')->label('نوع')->badge(),
                TextColumn::make('method')->label('روش'),
                TextColumn::make('amount')
                    ->label('مبلغ')
                    ->state(fn (BookingPayment $record) => ($record->kind === BookingPaymentKind::Refund ? '−' : '').Number::toPersian(number_format($record->amount)))
                    ->suffix(' تومان')
                    ->color(fn (BookingPayment $record) => $record->kind === BookingPaymentKind::Refund ? 'danger' : null),
                TextColumn::make('reference')->label('شماره پیگیری / رسید')->placeholder('—'),
                TextColumn::make('note')->label('یادداشت')->placeholder('—')->limit(40),
                TextColumn::make('creator.name')->label('ثبت‌کننده')->placeholder('—'),
            ])
            ->defaultSort('paid_at', 'desc')
            ->headerActions([
                RecordPaymentAction::make()->record(fn () => $this->getOwnerRecord()),
            ]);
    }
}
