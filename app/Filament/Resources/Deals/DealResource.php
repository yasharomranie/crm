<?php

namespace App\Filament\Resources\Deals;

use App\Filament\Resources\Deals\Pages\CreateDeal;
use App\Filament\Resources\Deals\Pages\EditDeal;
use App\Filament\Resources\Deals\Pages\ListDeals;
use App\Filament\Resources\Deals\Schemas\DealForm;
use App\Filament\Resources\Deals\Tables\DealsTable;
use App\Filament\Resources\Tasks\RelationManagers\FollowUpTasksRelationManager;
use App\Models\Deal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DealResource extends Resource
{
    protected static ?string $model = Deal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static string|\UnitEnum|null $navigationGroup = 'CRM و مشتریان';

    protected static ?string $modelLabel = 'معامله';

    protected static ?string $pluralModelLabel = 'معاملات فروش';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return DealForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DealsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
            FollowUpTasksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeals::route('/'),
            'create' => CreateDeal::route('/create'),
            'edit' => EditDeal::route('/{record}/edit'),
        ];
    }
}
