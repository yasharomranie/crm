<?php

namespace App\Filament\Resources\SeoContentItems\Pages;

use App\Filament\Resources\SeoContentItems\SeoContentItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSeoContentItems extends ListRecords
{
    protected static string $resource = SeoContentItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
