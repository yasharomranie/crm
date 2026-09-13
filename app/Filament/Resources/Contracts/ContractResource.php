<?php

namespace App\Filament\Resources\Contracts;

use App\Filament\Resources\Contracts\Pages\EditContract;
use App\Filament\Resources\Contracts\Pages\ListContracts;
use App\Filament\Resources\Contracts\Schemas\ContractForm;
use App\Filament\Resources\Contracts\Tables\ContractsTable;
use App\Models\Contract;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * A read-mostly archive: contracts are actually produced by the
 * "دستیار حقوقی و قرارداد" page (App\Filament\Pages\ContractAssistant),
 * so this resource offers no Create action - only listing, editing the
 * saved text/title, and deleting.
 */
class ContractResource extends Resource
{
    protected static ?string $model = Contract::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|\UnitEnum|null $navigationGroup = 'حقوقی و قرارداد';

    protected static ?string $modelLabel = 'قرارداد';

    protected static ?string $pluralModelLabel = 'آرشیو قراردادها';

    protected static ?string $recordTitleAttribute = 'title';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return ContractForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContractsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContracts::route('/'),
            'edit' => EditContract::route('/{record}/edit'),
        ];
    }
}
