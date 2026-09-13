<?php

namespace App\Filament\Resources\TaskChecklistTemplates\Schemas;

use App\Enums\ChecklistRecurrence;
use App\Filament\Support\FieldHelp;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TaskChecklistTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('نام قالب چک‌لیست')
                    ->helperText('مثلاً «چک‌لیست نظافت اتاق»')
                    ->required()
                    ->maxLength(255)
                    ->hintAction(FieldHelp::make('task-checklist-templates.name')),
                Select::make('department_id')
                    ->label('دپارتمان')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload()
                    ->hintAction(FieldHelp::make('task-checklist-templates.department_id')),
                Select::make('recurrence')
                    ->label('تکرار')
                    ->options(ChecklistRecurrence::class)
                    ->default(ChecklistRecurrence::Daily)
                    ->required()
                    ->helperText('روزانه یعنی هر روز یک نمونه‌ی جدید از این چک‌لیست به‌صورت خودکار ساخته می‌شود.')
                    ->hintAction(FieldHelp::make('task-checklist-templates.recurrence')),
                Toggle::make('is_active')
                    ->label('فعال')
                    ->default(true)
                    ->helperText('فقط قالب‌های فعال هر روز نمونه‌سازی می‌شوند.')
                    ->hintAction(FieldHelp::make('task-checklist-templates.is_active')),
            ]);
    }
}
