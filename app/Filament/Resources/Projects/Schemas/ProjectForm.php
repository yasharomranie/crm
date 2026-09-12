<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Enums\ProjectStatus;
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
                    ->maxLength(50),
                TextInput::make('title')
                    ->label('عنوان پروژه')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->label('اهداف و توضیحات')
                    ->columnSpanFull(),
                Select::make('department_id')
                    ->label('دپارتمان متولی')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('owner_id')
                    ->label('متولی اجرایی')
                    ->relationship('owner', 'name')
                    ->searchable()
                    ->preload(),
                TextInput::make('budget')
                    ->label('بودجه مصوب (تومان)')
                    ->numeric()
                    ->required(),
                Select::make('status')
                    ->label('وضعیت')
                    ->options(ProjectStatus::class)
                    ->default(ProjectStatus::Planning)
                    ->required(),
                DatePicker::make('start_date')
                    ->label('تاریخ شروع'),
                DatePicker::make('end_date')
                    ->label('تاریخ پایان (خط زمانی مصوب)')
                    ->afterOrEqual('start_date'),
            ]);
    }
}
