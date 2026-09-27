<?php

namespace App\Filament\Resources\ExternalApiClients\Pages;

use App\Filament\Resources\ExternalApiClients\ExternalApiClientResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExternalApiClients extends ListRecords
{
    protected static string $resource = ExternalApiClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
