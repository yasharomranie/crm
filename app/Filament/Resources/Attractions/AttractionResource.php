<?php

namespace App\Filament\Resources\Attractions;

use App\Filament\Resources\Attractions\Pages\CreateAttraction;
use App\Filament\Resources\Attractions\Pages\EditAttraction;
use App\Filament\Resources\Attractions\Pages\ListAttractions;
use App\Filament\Resources\Attractions\Schemas\AttractionForm;
use App\Filament\Resources\Attractions\Tables\AttractionsTable;
use App\Models\Attraction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AttractionResource extends Resource
{
    protected static ?string $model = Attraction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|\UnitEnum|null $navigationGroup = 'تریپ‌پلنر';

    protected static ?string $modelLabel = 'جاذبه گردشگری';

    protected static ?string $pluralModelLabel = 'جاذبه‌های گردشگری';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return AttractionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AttractionsTable::configure($table);
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
            'index' => ListAttractions::route('/'),
            'create' => CreateAttraction::route('/create'),
            'edit' => EditAttraction::route('/{record}/edit'),
        ];
    }
}
