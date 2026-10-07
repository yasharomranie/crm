<?php

namespace App\Filament\Resources\Attractions\Pages;

use App\Filament\Resources\Attractions\AttractionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAttraction extends EditRecord
{
    protected static string $resource = AttractionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
