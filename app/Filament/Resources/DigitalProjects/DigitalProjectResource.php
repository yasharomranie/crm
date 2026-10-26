<?php

namespace App\Filament\Resources\DigitalProjects;

use App\Filament\Resources\DigitalProjects\Pages\CreateDigitalProject;
use App\Filament\Resources\DigitalProjects\Pages\EditDigitalProject;
use App\Filament\Resources\DigitalProjects\Pages\ListDigitalProjects;
use App\Filament\Resources\DigitalProjects\Pages\ViewDigitalProject;
use App\Filament\Resources\DigitalProjects\RelationManagers\ActionsRelationManager;
use App\Filament\Resources\DigitalProjects\RelationManagers\LogsRelationManager;
use App\Filament\Resources\DigitalProjects\RelationManagers\StatusChangesRelationManager;
use App\Filament\Resources\DigitalProjects\Schemas\DigitalProjectForm;
use App\Filament\Resources\DigitalProjects\Schemas\DigitalProjectInfolist;
use App\Filament\Resources\DigitalProjects\Tables\DigitalProjectsTable;
use App\Models\DigitalProject;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DigitalProjectResource extends Resource
{
    protected static ?string $model = DigitalProject::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|\UnitEnum|null $navigationGroup = 'پروژه‌های دیجیتال';

    protected static ?string $navigationLabel = 'پروژه‌های دیجیتال';

    protected static ?string $modelLabel = 'پروژهٔ دیجیتال';

    protected static ?string $pluralModelLabel = 'پروژه‌های دیجیتال';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return DigitalProjectForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DigitalProjectInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DigitalProjectsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ActionsRelationManager::class,
            LogsRelationManager::class,
            StatusChangesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDigitalProjects::route('/'),
            'create' => CreateDigitalProject::route('/create'),
            'view' => ViewDigitalProject::route('/{record}'),
            'edit' => EditDigitalProject::route('/{record}/edit'),
        ];
    }
}
