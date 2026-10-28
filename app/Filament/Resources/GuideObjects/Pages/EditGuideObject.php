<?php

namespace App\Filament\Resources\GuideObjects\Pages;

use App\Filament\Resources\GuideObjects\GuideObjectResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGuideObject extends EditRecord
{
    protected static string $resource = GuideObjectResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $data + ['updated_by' => auth()->id()];
    }
}
