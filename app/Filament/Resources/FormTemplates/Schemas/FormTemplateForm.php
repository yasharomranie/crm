<?php

namespace App\Filament\Resources\FormTemplates\Schemas;

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
                    ->maxLength(255),
                Textarea::make('description')
                    ->label('توضیحات')
                    ->columnSpanFull(),
                Select::make('department_id')
                    ->label('دپارتمان مسئول')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('workflow_definition_id')
                    ->label('گردش‌کار تأیید (اختیاری)')
                    ->relationship('workflowDefinition', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('اگر انتخاب نشود، این فرم بدون نیاز به تأیید مستقیماً ثبت می‌شود.'),
                Toggle::make('is_active')
                    ->label('فعال')
                    ->default(true),
            ]);
    }
}
