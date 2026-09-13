<?php

namespace App\Filament\Resources\FormTemplates\Schemas;

use App\Filament\Support\FieldHelp;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class FormTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('عنوان فرم')
                    ->required()
                    ->maxLength(255)
                    ->hintAction(FieldHelp::make('form-templates.title')),
                Textarea::make('description')
                    ->label('توضیحات')
                    ->columnSpanFull()
                    ->hintAction(FieldHelp::make('form-templates.description')),
                Select::make('department_id')
                    ->label('دپارتمان مسئول')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload()
                    ->hintAction(FieldHelp::make('form-templates.department_id')),
                Select::make('workflow_definition_id')
                    ->label('گردش‌کار تأیید (اختیاری)')
                    ->relationship('workflowDefinition', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('اگر انتخاب نشود، این فرم بدون نیاز به تأیید مستقیماً ثبت می‌شود.')
                    ->hintAction(FieldHelp::make('form-templates.workflow_definition_id')),
                Toggle::make('is_active')
                    ->label('فعال')
                    ->default(true)
                    ->hintAction(FieldHelp::make('form-templates.is_active')),
            ]);
    }
}
