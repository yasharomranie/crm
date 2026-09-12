<?php

namespace App\Filament\Resources\WorkflowDefinitions\Pages;

use App\Filament\Resources\WorkflowDefinitions\WorkflowDefinitionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkflowDefinition extends EditRecord
{
    protected static string $resource = WorkflowDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
