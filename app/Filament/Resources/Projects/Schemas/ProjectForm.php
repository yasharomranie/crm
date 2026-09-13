<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Enums\ProjectStatus;
use App\Filament\Support\FieldHelp;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('کد یکتای پروژه')
                    ->helperText('مثلاً PRJ-IT-1405')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(50)
                    ->hintAction(FieldHelp::make('projects.code')),
                TextInput::make('title')
                    ->label('عنوان پروژه')
                    ->required()
                    ->maxLength(255)
                    ->hintAction(FieldHelp::make('projects.title')),
                Textarea::make('description')
                    ->label('اهداف و توضیحات')
                    ->columnSpanFull()
                    ->hintAction(FieldHelp::make('projects.description')),
                Select::make('department_id')
                    ->label('دپارتمان متولی')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload()
                    ->hintAction(FieldHelp::make('projects.department_id')),
                Select::make('owner_id')
                    ->label('متولی اجرایی')
                    ->relationship('owner', 'name')
                    ->searchable()
                    ->preload()
                    ->hintAction(FieldHelp::make('projects.owner_id')),
                TextInput::make('budget')
                    ->label('بودجه مصوب (تومان)')
                    ->numeric()
                    ->required()
                    ->hintAction(FieldHelp::make('projects.budget')),
                Select::make('status')
                    ->label('وضعیت')
                    ->options(ProjectStatus::class)
                    ->default(ProjectStatus::Planning)
                    ->required()
                    ->hintAction(FieldHelp::make('projects.status')),
                DatePicker::make('start_date')
                    ->label('تاریخ شروع')
                    ->hintAction(FieldHelp::make('projects.start_date')),
                DatePicker::make('end_date')
                    ->label('تاریخ پایان (خط زمانی مصوب)')
                    ->afterOrEqual('start_date')
                    ->hintAction(FieldHelp::make('projects.end_date')),
            ]);
    }
}
