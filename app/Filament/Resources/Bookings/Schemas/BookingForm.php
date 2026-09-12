<?php

namespace App\Filament\Resources\Bookings\Schemas;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Models\Guest;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

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
                    ->required(),
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
                    ->createOptionUsing(fn (array $data) => Guest::create($data)->id),
                DatePicker::make('check_in_date')
                    ->label('تاریخ ورود')
                    ->required()
                    ->default(now()),
                DatePicker::make('check_out_date')
                    ->label('تاریخ خروج')
                    ->required()
                    ->default(now()->addDay())
                    ->afterOrEqual('check_in_date'),
                TextInput::make('adults_count')
                    ->label('تعداد بزرگسال')
                    ->numeric()
                    ->default(1)
                    ->required(),
                TextInput::make('children_count')
                    ->label('تعداد کودک')
                    ->numeric()
                    ->default(0),
                Select::make('status')
                    ->label('وضعیت رزرو')
                    ->options(BookingStatus::class)
                    ->default(BookingStatus::Pending)
                    ->required()
                    ->helperText('برای چک‌این/چک‌اوت سریع، از دکمه‌های اختصاصی روی جدول رزروها استفاده کنید.'),
                Select::make('source')
                    ->label('منبع رزرو')
                    ->options(BookingSource::class)
                    ->default(BookingSource::WalkIn)
                    ->required(),
                Select::make('sales_channel_id')
                    ->label('کانال فروش')
                    ->relationship('channel', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('در صورت ورود از طریق سایت واسط (OTA)، کانال را انتخاب کنید تا کمیسیون آن هنگام تسویه محاسبه شود.'),
                Textarea::make('notes')
                    ->label('یادداشت')
                    ->columnSpanFull(),
            ]);
    }
}
