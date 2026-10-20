<?php

namespace App\Filament\Resources\ChargeItems\Pages;

use App\Filament\Resources\ChargeItems\ChargeItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageChargeItems extends ManageRecords
{
    protected static string $resource = ChargeItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
