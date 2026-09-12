<?php

namespace App\Filament\Resources\WorkflowDefinitions;

use App\Filament\Resources\WorkflowDefinitions\Pages\CreateWorkflowDefinition;
use App\Filament\Resources\WorkflowDefinitions\Pages\EditWorkflowDefinition;
use App\Filament\Resources\WorkflowDefinitions\Pages\ListWorkflowDefinitions;
use App\Filament\Resources\WorkflowDefinitions\RelationManagers\StepsRelationManager;
use App\Filament\Resources\WorkflowDefinitions\Schemas\WorkflowDefinitionForm;
use App\Filament\Resources\WorkflowDefinitions\Tables\WorkflowDefinitionsTable;
use App\Models\WorkflowDefinition;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WorkflowDefinitionResource extends Resource
{
    protected static ?string $model = WorkflowDefinition::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static string|\UnitEnum|null $navigationGroup = 'فرم‌ساز و گردش‌کار';

    protected static ?string $modelLabel = 'گردش‌کار تأیید';

    protected static ?string $pluralModelLabel = 'گردش‌کارهای تأیید';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return WorkflowDefinitionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkflowDefinitionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            StepsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkflowDefinitions::route('/'),
            'create' => CreateWorkflowDefinition::route('/create'),
            'edit' => EditWorkflowDefinition::route('/{record}/edit'),
        ];
    }
}
