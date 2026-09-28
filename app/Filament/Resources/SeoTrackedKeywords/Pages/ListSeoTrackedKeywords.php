<?php

namespace App\Filament\Resources\SeoTrackedKeywords\Pages;

use App\Filament\Resources\SeoTrackedKeywords\SeoTrackedKeywordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSeoTrackedKeywords extends ListRecords
{
    protected static string $resource = SeoTrackedKeywordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
