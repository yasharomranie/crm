<?php

namespace App\Filament\Resources\SeoContentItems;

use App\Filament\Resources\SeoContentItems\Pages\CreateSeoContentItem;
use App\Filament\Resources\SeoContentItems\Pages\EditSeoContentItem;
use App\Filament\Resources\SeoContentItems\Pages\ListSeoContentItems;
use App\Filament\Resources\SeoContentItems\Schemas\SeoContentItemForm;
use App\Filament\Resources\SeoContentItems\Tables\SeoContentItemsTable;
use App\Models\SeoContentItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SeoContentItemResource extends Resource
{
    protected static ?string $model = SeoContentItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'مرکز سئو';

    protected static ?string $navigationLabel = 'تقویم محتوا';

    protected static ?string $modelLabel = 'آیتم محتوا';

    protected static ?string $pluralModelLabel = 'تقویم محتوا';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return SeoContentItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SeoContentItemsTable::configure($table);
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
            'index' => ListSeoContentItems::route('/'),
            'create' => CreateSeoContentItem::route('/create'),
            'edit' => EditSeoContentItem::route('/{record}/edit'),
        ];
    }
}
