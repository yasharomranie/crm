<?php

namespace App\Filament\Resources\ContractCategories\Pages;

use App\Filament\Resources\ContractCategories\ContractCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageContractCategories extends ManageRecords
{
    protected static string $resource = ContractCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
