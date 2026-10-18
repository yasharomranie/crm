<?php

namespace App\Filament\Resources\ExternalApiClients;

use App\Filament\Resources\ExternalApiClients\Pages\CreateExternalApiClient;
use App\Filament\Resources\ExternalApiClients\Pages\EditExternalApiClient;
use App\Filament\Resources\ExternalApiClients\Pages\ListExternalApiClients;
use App\Filament\Resources\ExternalApiClients\Schemas\ExternalApiClientForm;
use App\Filament\Resources\ExternalApiClients\Tables\ExternalApiClientsTable;
use App\Models\ExternalApiClient;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ExternalApiClientResource extends Resource
{
    protected static ?string $model = ExternalApiClient::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|\UnitEnum|null $navigationGroup = 'زیرساخت سازمانی';

    protected static ?string $navigationLabel = 'اکانت‌های API خارجی';

    protected static ?string $modelLabel = 'اکانت API خارجی';

    protected static ?string $pluralModelLabel = 'اکانت‌های API خارجی';

    protected static ?string $recordTitleAttribute = 'client_name';

    public static function form(Schema $schema): Schema
    {
        return ExternalApiClientForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExternalApiClientsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\WebhookSubscriptionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExternalApiClients::route('/'),
            'create' => CreateExternalApiClient::route('/create'),
            'edit' => EditExternalApiClient::route('/{record}/edit'),
        ];
    }
}
