<?php

namespace App\Filament\Resources\DiscountCodes\Schemas;

use App\Enums\DiscountType;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DiscountCodeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('کد تخفیف')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(50)
                    ->alphaDash(),
                Select::make('discount_type')
                    ->label('نوع تخفیف')
                    ->options(DiscountType::class)
                    ->default(DiscountType::Percentage)
                    ->live()
                    ->required(),
                TextInput::make('discount_value')
                    ->label(fn ($get) => $get('discount_type') === DiscountType::FixedAmount->value ? 'مبلغ تخفیف (ریال)' : 'درصد تخفیف')
                    ->numeric()
                    ->required(),
                DateTimePicker::make('start_date')->label('تاریخ شروع'),
                DateTimePicker::make('end_date')->label('تاریخ پایان'),
                TextInput::make('usage_limit')
                    ->label('سقف کل استفاده (خالی = نامحدود)')
                    ->numeric(),
                TextInput::make('per_customer_usage_limit')
                    ->label('سقف استفاده هر مشتری (خالی = نامحدود)')
                    ->numeric(),
                TextInput::make('minimum_purchase_amount')
                    ->label('حداقل مبلغ خرید (ریال)')
                    ->numeric(),
                TextInput::make('maximum_discount_amount')
                    ->label('حداکثر مبلغ تخفیف (ریال)')
                    ->numeric(),
                Select::make('products')
                    ->label('محدود به محصولات (خالی = همه)')
                    ->relationship('products', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),
                Select::make('ticketVariants')
                    ->label('محدود به انواع بلیت (خالی = همه)')
                    ->relationship('ticketVariants', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),
                Toggle::make('is_active')
                    ->label('فعال')
                    ->default(true),
            ]);
    }
}
