<?php

namespace App\Filament\Resources\LostItems\Pages;

use App\Filament\Resources\LostItems\LostItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageLostItems extends ManageRecords
{
    protected static string $resource = LostItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
