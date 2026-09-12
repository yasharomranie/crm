<?php

namespace App\Filament\Resources\GuestFeedback\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class GuestFeedbackForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('guest_id')
                    ->label('مشتری')
                    ->relationship('guest', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('booking_id')
                    ->label('رزرو مرتبط')
                    ->relationship('booking', 'reference_code')
                    ->searchable(),
                TextInput::make('nps_score')
                    ->label('امتیاز NPS (۰ تا ۱۰)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(10),
                Textarea::make('comment')
                    ->label('نظر مسافر')
                    ->columnSpanFull(),
                DateTimePicker::make('submitted_at')
                    ->label('زمان ثبت پاسخ'),
            ]);
    }
}
