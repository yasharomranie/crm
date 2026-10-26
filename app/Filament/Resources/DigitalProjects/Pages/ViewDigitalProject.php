<?php

namespace App\Filament\Resources\DigitalProjects\Pages;

use App\Filament\Resources\DigitalProjects\DigitalProjectResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDigitalProject extends ViewRecord
{
    protected static string $resource = DigitalProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
