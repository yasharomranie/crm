<?php

namespace App\Filament\Resources\WebAppSections\Pages;

use App\Filament\Resources\WebAppSections\WebAppSectionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageWebAppSections extends ManageRecords
{
    protected static string $resource = WebAppSectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
