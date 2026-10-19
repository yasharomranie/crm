<?php

namespace App\Filament\Resources\StayDiscounts\Pages;

use App\Filament\Resources\StayDiscounts\StayDiscountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageStayDiscounts extends ManageRecords
{
    protected static string $resource = StayDiscountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
