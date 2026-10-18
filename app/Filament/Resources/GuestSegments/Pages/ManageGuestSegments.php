<?php

namespace App\Filament\Resources\GuestSegments\Pages;

use App\Filament\Resources\GuestSegments\GuestSegmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageGuestSegments extends ManageRecords
{
    protected static string $resource = GuestSegmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
