<?php

namespace App\Filament\Resources\Rooms\Schemas;

use App\Enums\RoomStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class RoomForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('نام/شماره واحد')
                    ->helperText('مثلاً «ویلا ۱۰۱» یا «اتاق ۲۰۵»')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Select::make('room_type_id')
                    ->label('نوع اتاق/ویلا')
                    ->relationship('roomType', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('department_id')
                    ->label('دپارتمان مسئول نظافت')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('تسک نظافتی که بعد از چک‌اوت خودکار ساخته می‌شود، به این دپارتمان تعلق می‌گیرد.'),
                Select::make('status')
                    ->label('وضعیت فعلی')
                    ->options(RoomStatus::class)
                    ->default(RoomStatus::Available)
                    ->required(),
                Toggle::make('is_active')
                    ->label('فعال (در دسترس برای رزرو)')
                    ->default(true),
            ]);
    }
}
