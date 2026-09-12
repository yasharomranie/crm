<?php

namespace App\Filament\Resources\WorkflowDefinitions\Schemas;

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
                    ->maxLength(255),
                Select::make('subject_type')
                    ->label('این گردش‌کار روی چه چیزی اعمال می‌شود؟')
                    ->options([
                        FormSubmission::class => 'ثبت فرم',
                        Task::class => 'تسک',
                    ])
                    ->required(),
                Toggle::make('is_active')
                    ->label('فعال')
                    ->default(true),
            ]);
    }
}
