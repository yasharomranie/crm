<?php

namespace App\Filament\Resources\SeoContentItems\Pages;

use App\Filament\Resources\SeoContentItems\SeoContentItemResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSeoContentItem extends EditRecord
{
    protected static string $resource = SeoContentItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
