<?php

namespace App\Filament\Resources\ContractCompanies\Pages;

use App\Filament\Resources\ContractCompanies\ContractCompanyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageContractCompanies extends ManageRecords
{
    protected static string $resource = ContractCompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
