<?php

namespace App\Filament\Resources\TaskChecklistTemplates\Pages;

use App\Filament\Resources\TaskChecklistTemplates\TaskChecklistTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTaskChecklistTemplates extends ListRecords
{
    protected static string $resource = TaskChecklistTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
