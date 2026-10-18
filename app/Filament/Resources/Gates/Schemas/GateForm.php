<?php

namespace App\Filament\Resources\Gates\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class GateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('نام گیت')
                    ->required()
                    ->maxLength(255),
                TextInput::make('code')
                    ->label('کد گیت')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(50),
                Select::make('kind')
                    ->label('نوع گیت')
                    ->options(['both' => 'ورود و خروج', 'entry' => 'فقط ورود', 'exit' => 'فقط خروج'])
                    ->default('both')
                    ->required()
                    ->helperText('برای شمارنده‌ی «چند نفر الان داخل هستند» لازم است: ورود +۱ و خروج -۱ حساب می‌شود.'),
                Select::make('product_id')
                    ->label('محصول (خالی = عمومی)')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),
                Textarea::make('description')
                    ->label('توضیحات')
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('فعال')
                    ->default(true),
            ]);
    }
}
