<?php

namespace App\Filament\Resources\Bookings\Schemas;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Filament\Support\FieldHelp;
use App\Models\Booking;
use App\Models\Guest;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;

class BookingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('room_id')
                    ->label('اتاق/ویلا')
                    ->relationship('room', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->hintAction(FieldHelp::make('bookings.room_id')),
                Select::make('guest_id')
                    ->label('مهمان')
                    ->relationship('guest', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->createOptionForm([
                        TextInput::make('name')->label('نام و نام خانوادگی')->required(),
                        TextInput::make('mobile')->label('شماره موبایل')->tel(),
                    ])
                    ->createOptionUsing(fn (array $data) => Guest::create($data)->id)
                    ->hintAction(FieldHelp::make('bookings.guest_id')),
                DatePicker::make('check_in_date')
                    ->jalali()
                    ->displayFormat('Y/m/d')
                    ->label('تاریخ ورود')
                    ->required()
                    ->default(now())
                    ->hintAction(FieldHelp::make('bookings.check_in_date')),
                DatePicker::make('check_out_date')
                    ->jalali()
                    ->displayFormat('Y/m/d')
                    ->label('تاریخ خروج')
                    ->required()
                    ->default(now()->addDay())
                    ->afterOrEqual('check_in_date')
                    ->hintAction(FieldHelp::make('bookings.check_out_date')),
                TextInput::make('adults_count')
                    ->label('تعداد بزرگسال')
                    ->numeric()
                    ->default(1)
                    ->required()
                    ->hintAction(FieldHelp::make('bookings.adults_count')),
                TextInput::make('children_count')
                    ->label('تعداد کودک')
                    ->numeric()
                    ->default(0)
                    ->hintAction(FieldHelp::make('bookings.children_count')),
                Select::make('status')
                    ->label('وضعیت رزرو')
                    ->options(BookingStatus::class)
                    ->default(BookingStatus::Pending)
                    ->required()
                    ->helperText('برای چک‌این/چک‌اوت سریع، از دکمه‌های اختصاصی روی جدول رزروها استفاده کنید.')
                    ->hintAction(FieldHelp::make('bookings.status')),
                Select::make('source')
                    ->label('منبع رزرو')
                    ->options(BookingSource::class)
                    ->default(BookingSource::WalkIn)
                    ->required()
                    ->hintAction(FieldHelp::make('bookings.source')),
                Select::make('sales_channel_id')
                    ->label('کانال فروش')
                    ->relationship('channel', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('در صورت ورود از طریق سایت واسط (OTA)، کانال را انتخاب کنید تا کمیسیون آن هنگام تسویه محاسبه شود.')
                    ->hintAction(FieldHelp::make('bookings.sales_channel_id')),
                Textarea::make('notes')
                    ->label('یادداشت')
                    ->columnSpanFull()
                    ->hintAction(FieldHelp::make('bookings.notes')),
                Section::make('خلاصه‌ی صورت‌حساب')
                    ->visible(fn (?Booking $record) => $record !== null)
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        Placeholder::make('folio_total')
                            ->label('کل صورت‌حساب')
                            ->content(fn (?Booking $record) => self::money($record?->totalAmount() ?? 0)),
                        Placeholder::make('folio_paid')
                            ->label('پرداخت‌شده (کیف پول + دریافتی)')
                            ->content(fn (?Booking $record) => self::money($record?->paidAmount() ?? 0)),
                        Placeholder::make('folio_balance')
                            ->label('مانده')
                            ->content(fn (?Booking $record) => self::money($record?->balanceDue() ?? 0)),
                        Placeholder::make('folio_status')
                            ->label('وضعیت پرداخت')
                            ->content(fn (?Booking $record) => $record?->paymentStatus()->getLabel() ?? '—'),
                    ]),
            ]);
    }

    private static function money(int $amount): string
    {
        return Number::toPersian(number_format($amount)).' تومان';
    }
}
