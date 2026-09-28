<?php

namespace App\Filament\Resources\SeoOpportunities\Pages;

use App\Filament\Resources\SeoOpportunities\SeoOpportunityResource;
use Filament\Resources\Pages\ManageRecords;

class ManageSeoOpportunities extends ManageRecords
{
    protected static string $resource = SeoOpportunityResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
