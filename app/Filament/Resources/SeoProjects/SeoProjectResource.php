<?php

namespace App\Filament\Resources\SeoProjects;

use App\Filament\Resources\SeoProjects\Pages\CreateSeoProject;
use App\Filament\Resources\SeoProjects\Pages\EditSeoProject;
use App\Filament\Resources\SeoProjects\Pages\ListSeoProjects;
use App\Filament\Resources\SeoProjects\Schemas\SeoProjectForm;
use App\Filament\Resources\SeoProjects\Tables\SeoProjectsTable;
use App\Models\SeoProject;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SeoProjectResource extends Resource
{
    protected static ?string $model = SeoProject::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static string|\UnitEnum|null $navigationGroup = 'مرکز سئو';

    protected static ?string $navigationLabel = 'پروژه‌های سئو';

    protected static ?string $modelLabel = 'پروژه‌ی سئو';

    protected static ?string $pluralModelLabel = 'پروژه‌های سئو';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return SeoProjectForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SeoProjectsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSeoProjects::route('/'),
            'create' => CreateSeoProject::route('/create'),
            'edit' => EditSeoProject::route('/{record}/edit'),
        ];
    }
}
