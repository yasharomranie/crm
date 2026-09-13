<?php

namespace App\Filament\Resources\DocumentFolders\Pages;

use App\Filament\Resources\DocumentFolders\DocumentFolderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDocumentFolders extends ManageRecords
{
    protected static string $resource = DocumentFolderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
