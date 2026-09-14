<?php

namespace App\Filament\Resources\GuestFeedback\Schemas;

use App\Filament\Support\FieldHelp;
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
                    ->required()
                    ->hintAction(FieldHelp::make('guest-feedback.guest_id')),
                Select::make('booking_id')
                    ->label('رزرو مرتبط')
                    ->relationship('booking', 'reference_code')
                    ->searchable()
                    ->hintAction(FieldHelp::make('guest-feedback.booking_id')),
                TextInput::make('nps_score')
                    ->label('امتیاز NPS (۰ تا ۱۰)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(10)
                    ->hintAction(FieldHelp::make('guest-feedback.nps_score')),
                Textarea::make('comment')
                    ->label('نظر مسافر')
                    ->columnSpanFull()
                    ->hintAction(FieldHelp::make('guest-feedback.comment')),
                DateTimePicker::make('submitted_at')
                    ->jalali()
                    ->displayFormat('Y/m/d H:i')
                    ->label('زمان ثبت پاسخ')
                    ->hintAction(FieldHelp::make('guest-feedback.submitted_at')),
            ]);
    }
}
