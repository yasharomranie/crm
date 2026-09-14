<?php

namespace App\Filament\Resources\Deals\Schemas;

use App\Enums\DealSource;
use App\Enums\DealStage;
use App\Filament\Support\FieldHelp;
use App\Models\Guest;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DealForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('عنوان معامله')
                    ->required()
                    ->maxLength(255)
                    ->hintAction(FieldHelp::make('deals.title')),
                Select::make('guest_id')
                    ->label('مشتری')
                    ->relationship('guest', 'name')
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('name')->label('نام و نام خانوادگی')->required(),
                        TextInput::make('mobile')->label('شماره موبایل')->tel(),
                    ])
                    ->createOptionUsing(fn (array $data) => Guest::create($data)->id)
                    ->hintAction(FieldHelp::make('deals.guest_id')),
                Select::make('stage')
                    ->label('مرحله پایپ‌لاین')
                    ->options(DealStage::class)
                    ->default(DealStage::NewLead)
                    ->required()
                    ->hintAction(FieldHelp::make('deals.stage')),
                TextInput::make('value')
                    ->label('ارزش تخمینی (تومان)')
                    ->numeric()
                    ->hintAction(FieldHelp::make('deals.value')),
                Select::make('assigned_to')
                    ->label('کارشناس فروش')
                    ->relationship('assignee', 'name')
                    ->searchable()
                    ->preload()
                    ->hintAction(FieldHelp::make('deals.assigned_to')),
                Select::make('source')
                    ->label('منبع سرنخ')
                    ->options(DealSource::class)
                    ->default(DealSource::Other)
                    ->hintAction(FieldHelp::make('deals.source')),
                DatePicker::make('expected_close_date')
                    ->jalali()
                    ->displayFormat('Y/m/d')
                    ->label('تاریخ تخمینی قطعی‌شدن')
                    ->hintAction(FieldHelp::make('deals.expected_close_date')),
                Textarea::make('notes')
                    ->label('یادداشت')
                    ->columnSpanFull()
                    ->hintAction(FieldHelp::make('deals.notes')),
            ]);
    }
}
