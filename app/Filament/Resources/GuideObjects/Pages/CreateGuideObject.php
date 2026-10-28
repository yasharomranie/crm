<?php

namespace App\Filament\Resources\GuideObjects\Pages;

use App\Filament\Resources\GuideObjects\GuideObjectResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGuideObject extends CreateRecord
{
    protected static string $resource = GuideObjectResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data + ['created_by' => auth()->id(), 'updated_by' => auth()->id()];
    }
}
