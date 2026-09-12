<?php

namespace App\Filament\Resources\TaskChecklistTemplates;

use App\Filament\Resources\TaskChecklistTemplates\Pages\CreateTaskChecklistTemplate;
use App\Filament\Resources\TaskChecklistTemplates\Pages\EditTaskChecklistTemplate;
use App\Filament\Resources\TaskChecklistTemplates\Pages\ListTaskChecklistTemplates;
use App\Filament\Resources\TaskChecklistTemplates\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\TaskChecklistTemplates\Schemas\TaskChecklistTemplateForm;
use App\Filament\Resources\TaskChecklistTemplates\Tables\TaskChecklistTemplatesTable;
use App\Models\TaskChecklistTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TaskChecklistTemplateResource extends Resource
{
    protected static ?string $model = TaskChecklistTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'وظایف و پروژه‌ها';

    protected static ?string $modelLabel = 'قالب چک‌لیست تکرارشونده';

    protected static ?string $pluralModelLabel = 'قالب‌های چک‌لیست تکرارشونده';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return TaskChecklistTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TaskChecklistTemplatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTaskChecklistTemplates::route('/'),
            'create' => CreateTaskChecklistTemplate::route('/create'),
            'edit' => EditTaskChecklistTemplate::route('/{record}/edit'),
        ];
    }
}
