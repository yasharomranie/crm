<?php

namespace App\Filament\Resources\TaskChecklistTemplates\Pages;

use App\Filament\Resources\TaskChecklistTemplates\TaskChecklistTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTaskChecklistTemplate extends EditRecord
{
    protected static string $resource = TaskChecklistTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
