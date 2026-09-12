<?php

namespace App\Filament\Resources\Tasks\Schemas;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Tag;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TaskForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('عنوان تسک')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->label('توضیحات')
                    ->columnSpanFull(),
                Select::make('department_id')
                    ->label('دپارتمان')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('assigned_to')
                    ->label('مسئول انجام')
                    ->relationship('assignee', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('priority')
                    ->label('اولویت (ماتریس آیزنهاور)')
                    ->options(TaskPriority::class)
                    ->default(TaskPriority::Neither)
                    ->required(),
                Select::make('status')
                    ->label('وضعیت')
                    ->options(TaskStatus::class)
                    ->default(TaskStatus::Todo)
                    ->required(),
                DateTimePicker::make('due_at')
                    ->label('مهلت انجام'),
                Select::make('tags')
                    ->label('برچسب‌ها')
                    ->relationship('tags', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('name')->label('نام برچسب')->required(),
                    ])
                    ->createOptionUsing(fn (array $data) => Tag::create($data)->id)
                    ->columnSpanFull(),
            ]);
    }
}
