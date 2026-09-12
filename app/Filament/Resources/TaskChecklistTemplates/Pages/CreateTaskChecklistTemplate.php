<?php

namespace App\Filament\Resources\TaskChecklistTemplates\Pages;

use App\Filament\Resources\TaskChecklistTemplates\TaskChecklistTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTaskChecklistTemplate extends CreateRecord
{
    protected static string $resource = TaskChecklistTemplateResource::class;
}
