<?php

namespace App\Filament\Resources\Tasks\Schemas;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Filament\Support\FieldHelp;
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
                    ->columnSpanFull()
                    ->hintAction(FieldHelp::make('tasks.title')),
                Textarea::make('description')
                    ->label('توضیحات')
                    ->columnSpanFull()
                    ->hintAction(FieldHelp::make('tasks.description')),
                Select::make('department_id')
                    ->label('دپارتمان')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload()
                    ->hintAction(FieldHelp::make('tasks.department_id')),
                Select::make('assigned_to')
                    ->label('مسئول انجام')
                    ->relationship('assignee', 'name')
                    ->searchable()
                    ->preload()
                    ->hintAction(FieldHelp::make('tasks.assigned_to')),
                Select::make('priority')
                    ->label('اولویت (ماتریس آیزنهاور)')
                    ->options(TaskPriority::class)
                    ->default(TaskPriority::Neither)
                    ->required()
                    ->hintAction(FieldHelp::make('tasks.priority')),
                Select::make('status')
                    ->label('وضعیت')
                    ->options(TaskStatus::class)
                    ->default(TaskStatus::Todo)
                    ->required()
                    ->hintAction(FieldHelp::make('tasks.status')),
                DateTimePicker::make('due_at')
                    ->label('مهلت انجام')
                    ->hintAction(FieldHelp::make('tasks.due_at')),
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
                    ->columnSpanFull()
                    ->hintAction(FieldHelp::make('tasks.tags')),
            ]);
    }
}
