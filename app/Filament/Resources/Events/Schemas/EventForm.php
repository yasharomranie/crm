<?php

namespace App\Filament\Resources\Events\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('عنوان رویداد')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->label('توضیحات')
                    ->rows(3)
                    ->columnSpanFull(),
                DateTimePicker::make('starts_at')
                    ->jalali()
                    ->displayFormat('Y/m/d H:i')
                    ->label('تاریخ و ساعت رویداد')
                    ->native(false)
                    ->seconds(false)
                    ->required(),
                Select::make('participants')
                    ->label('شرکت‌کنندگان (گیرندگان یادآوری)')
                    ->relationship('participants', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->helperText('اگر کسی انتخاب نشود، یادآوری‌ها فقط برای ایجادکننده‌ی رویداد ارسال می‌شود.')
                    ->default(fn () => Auth::id() ? [Auth::id()] : []),
                Hidden::make('created_by')
                    ->default(fn () => Auth::id()),
            ]);
    }
}
