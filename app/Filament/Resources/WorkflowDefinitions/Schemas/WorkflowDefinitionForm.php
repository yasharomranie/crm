<?php

namespace App\Filament\Resources\WorkflowDefinitions\Schemas;

use App\Filament\Support\FieldHelp;
use App\Models\FormSubmission;
use App\Models\Task;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class WorkflowDefinitionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('نام گردش‌کار')
                    ->required()
                    ->maxLength(255)
                    ->hintAction(FieldHelp::make('workflow-definitions.name')),
                Select::make('subject_type')
                    ->label('این گردش‌کار روی چه چیزی اعمال می‌شود؟')
                    ->options([
                        FormSubmission::class => 'ثبت فرم',
                        Task::class => 'تسک',
                    ])
                    ->required()
                    ->hintAction(FieldHelp::make('workflow-definitions.subject_type')),
                Toggle::make('is_active')
                    ->label('فعال')
                    ->default(true)
                    ->hintAction(FieldHelp::make('workflow-definitions.is_active')),
            ]);
    }
}
