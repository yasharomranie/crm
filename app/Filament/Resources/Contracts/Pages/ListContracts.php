<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use Filament\Resources\Pages\ListRecords;

class ListContracts extends ListRecords
{
    protected static string $resource = ContractResource::class;

    protected function getHeaderActions(): array
    {
        // No CreateAction here on purpose: contracts are produced by the
        // "دستیار حقوقی و قرارداد" page, not through a manual form.
        return [];
    }
}
