<?php

namespace App\Filament\Resources\TicketVariants\Schemas;

use App\Enums\TicketDirection;
use App\Models\TicketTemplate;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TicketVariantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_id')
                    ->label('محصول')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('name')
                    ->label('نام (مثلاً «رفت»، «برگشت»، «رفت و برگشت»)')
                    ->required()
                    ->maxLength(255),
                TextInput::make('code')
                    ->label('کد')
                    ->required()
                    ->maxLength(50),
                Textarea::make('description')
                    ->label('توضیحات')
                    ->columnSpanFull(),
                Select::make('direction')
                    ->label('جهت (فقط برای محصولات دارای رفت/برگشت)')
                    ->options(TicketDirection::class)
                    ->helperText('برای بلیت رفت‌وبرگشت خالی بگذارید - آن با تعداد دفعات مصرف و قوانین گیت مشخص می‌شود.'),
                TextInput::make('allowed_uses')
                    ->label('تعداد دفعات مجاز مصرف')
                    ->numeric()
                    ->minValue(1)
                    ->default(1)
                    ->required()
                    ->helperText('۱ برای بلیت یک‌طرفه، ۲ برای رفت‌وبرگشت.'),
                TextInput::make('default_validity_days')
                    ->label('مدت اعتبار (روز از زمان صدور)')
                    ->numeric()
                    ->minValue(1)
                    ->default(1)
                    ->required(),
                Select::make('ticket_template_id')
                    ->label('قالب چاپ بلیت')
                    ->options(fn () => TicketTemplate::query()->pluck('name', 'id'))
                    ->searchable(),
                Toggle::make('is_active')
                    ->label('فعال')
                    ->default(true),
                TextInput::make('sort_order')
                    ->label('ترتیب نمایش')
                    ->numeric()
                    ->default(0),
            ]);
    }
}
