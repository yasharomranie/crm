<?php

namespace App\Filament\Resources\GuideObjects\Pages;

use App\Filament\Resources\GuideObjects\GuideObjectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGuideObjects extends ListRecords
{
    protected static string $resource = GuideObjectResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
