<?php

namespace App\Filament\Resources\Deals\Schemas;

use App\Enums\DealSource;
use App\Enums\DealStage;
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
                    ->maxLength(255),
                Select::make('guest_id')
                    ->label('مشتری')
                    ->relationship('guest', 'name')
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('name')->label('نام و نام خانوادگی')->required(),
                        TextInput::make('mobile')->label('شماره موبایل')->tel(),
                    ])
                    ->createOptionUsing(fn (array $data) => Guest::create($data)->id),
                Select::make('stage')
                    ->label('مرحله پایپ‌لاین')
                    ->options(DealStage::class)
                    ->default(DealStage::NewLead)
                    ->required(),
                TextInput::make('value')
                    ->label('ارزش تخمینی (تومان)')
                    ->numeric(),
                Select::make('assigned_to')
                    ->label('کارشناس فروش')
                    ->relationship('assignee', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('source')
                    ->label('منبع سرنخ')
                    ->options(DealSource::class)
                    ->default(DealSource::Other),
                DatePicker::make('expected_close_date')
                    ->label('تاریخ تخمینی قطعی‌شدن'),
                Textarea::make('notes')
                    ->label('یادداشت')
                    ->columnSpanFull(),
            ]);
    }
}
