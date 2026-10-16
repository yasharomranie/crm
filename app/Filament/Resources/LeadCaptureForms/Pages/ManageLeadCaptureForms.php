<?php

namespace App\Filament\Resources\LeadCaptureForms\Pages;

use App\Filament\Resources\LeadCaptureForms\LeadCaptureFormResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageLeadCaptureForms extends ManageRecords
{
    protected static string $resource = LeadCaptureFormResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
