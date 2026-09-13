<?php

namespace App\Filament\Resources\Rooms\Schemas;

use App\Enums\RoomStatus;
use App\Filament\Support\FieldHelp;
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
                    ->maxLength(255)
                    ->hintAction(FieldHelp::make('rooms.name')),
                Select::make('room_type_id')
                    ->label('نوع اتاق/ویلا')
                    ->relationship('roomType', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->hintAction(FieldHelp::make('rooms.room_type_id')),
                Select::make('department_id')
                    ->label('دپارتمان مسئول نظافت')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('تسک نظافتی که بعد از چک‌اوت خودکار ساخته می‌شود، به این دپارتمان تعلق می‌گیرد.')
                    ->hintAction(FieldHelp::make('rooms.department_id')),
                Select::make('status')
                    ->label('وضعیت فعلی')
                    ->options(RoomStatus::class)
                    ->default(RoomStatus::Available)
                    ->required()
                    ->hintAction(FieldHelp::make('rooms.status')),
                Toggle::make('is_active')
                    ->label('فعال (در دسترس برای رزرو)')
                    ->default(true)
                    ->hintAction(FieldHelp::make('rooms.is_active')),
            ]);
    }
}
