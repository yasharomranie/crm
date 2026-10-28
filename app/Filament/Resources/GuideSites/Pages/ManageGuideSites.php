<?php

namespace App\Filament\Resources\GuideSites\Pages;

use App\Filament\Resources\GuideSites\GuideSiteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageGuideSites extends ManageRecords
{
    protected static string $resource = GuideSiteResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
