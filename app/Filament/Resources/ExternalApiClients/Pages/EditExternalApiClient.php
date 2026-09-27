<?php

namespace App\Filament\Resources\ExternalApiClients\Pages;

use App\Filament\Resources\ExternalApiClients\ExternalApiClientResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditExternalApiClient extends EditRecord
{
    protected static string $resource = ExternalApiClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
