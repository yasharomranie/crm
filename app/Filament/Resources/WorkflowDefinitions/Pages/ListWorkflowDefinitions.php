<?php

namespace App\Filament\Resources\WorkflowDefinitions\Pages;

use App\Filament\Resources\WorkflowDefinitions\WorkflowDefinitionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkflowDefinitions extends ListRecords
{
    protected static string $resource = WorkflowDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
