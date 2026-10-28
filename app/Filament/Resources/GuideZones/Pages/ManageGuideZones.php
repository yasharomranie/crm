<?php

namespace App\Filament\Resources\GuideZones\Pages;

use App\Filament\Resources\GuideZones\GuideZoneResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageGuideZones extends ManageRecords
{
    protected static string $resource = GuideZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
