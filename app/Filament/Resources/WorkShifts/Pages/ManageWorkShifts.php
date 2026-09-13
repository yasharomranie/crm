<?php

namespace App\Filament\Resources\WorkShifts\Pages;

use App\Filament\Resources\WorkShifts\WorkShiftResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageWorkShifts extends ManageRecords
{
    protected static string $resource = WorkShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
