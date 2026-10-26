<?php

namespace App\Filament\Resources\DigitalProjects\Schemas;

use App\Enums\DigitalProjectChannel;
use App\Enums\DigitalProjectStatus;
use App\Filament\Support\FieldHelp;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DigitalProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('channel')
                    ->label('نوع پروژه')
                    ->options(DigitalProjectChannel::class)
                    ->required()
                    ->hintAction(FieldHelp::make('digital-projects.channel')),
                TextInput::make('name')
                    ->label('نام پروژه')
                    ->required()
                    ->maxLength(150)
                    ->hintAction(FieldHelp::make('digital-projects.name')),
                TextInput::make('handle')
                    ->label('آدرس یا آیدی')
                    ->maxLength(255)
                    ->hintAction(FieldHelp::make('digital-projects.handle')),
                Select::make('status')
                    ->label('وضعیت')
                    ->options(DigitalProjectStatus::class)
                    ->default(DigitalProjectStatus::Planning)
                    ->required()
                    ->hintAction(FieldHelp::make('digital-projects.status')),
                Select::make('owner_id')
                    ->label('مسئول')
                    ->relationship('owner', 'name')
                    ->searchable()
                    ->preload()
                    ->hintAction(FieldHelp::make('digital-projects.owner_id')),
                DatePicker::make('next_review_at')
                    ->jalali()
                    ->displayFormat('Y/m/d')
                    ->label('بررسی بعدی')
                    ->hintAction(FieldHelp::make('digital-projects.next_review_at')),
                Textarea::make('description')
                    ->label('شرح پروژه')
                    ->rows(8)
                    ->columnSpanFull()
                    ->hintAction(FieldHelp::make('digital-projects.description')),
            ]);
    }
}
