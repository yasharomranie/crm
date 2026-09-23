<?php

namespace App\Filament\Resources\TicketVariants;

use App\Filament\Resources\TicketVariants\Pages\CreateTicketVariant;
use App\Filament\Resources\TicketVariants\Pages\EditTicketVariant;
use App\Filament\Resources\TicketVariants\Pages\ListTicketVariants;
use App\Filament\Resources\TicketVariants\RelationManagers\PricesRelationManager;
use App\Filament\Resources\TicketVariants\Schemas\TicketVariantForm;
use App\Filament\Resources\TicketVariants\Tables\TicketVariantsTable;
use App\Models\TicketVariant;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TicketVariantResource extends Resource
{
    protected static ?string $model = TicketVariant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|\UnitEnum|null $navigationGroup = 'گیشه بلیط';

    protected static ?string $navigationLabel = 'انواع بلیت';

    protected static ?string $modelLabel = 'نوع بلیط';

    protected static ?string $pluralModelLabel = 'انواع بلیت';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return TicketVariantForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TicketVariantsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PricesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTicketVariants::route('/'),
            'create' => CreateTicketVariant::route('/create'),
            'edit' => EditTicketVariant::route('/{record}/edit'),
        ];
    }
}
