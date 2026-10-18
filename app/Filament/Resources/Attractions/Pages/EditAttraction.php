<?php

namespace App\Filament\Resources\Attractions\Pages;

use App\Filament\Resources\Attractions\AttractionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAttraction extends EditRecord
{
    protected static string $resource = AttractionResource::class;

    /**
     * "Verified" is a checkbox that is not a column: it stamps (or clears)
     * verified_at, which is what lets an AI-suggested place go live.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $verified = $this->data['verified'] ?? null;

        if ($verified !== null) {
            $data['verified_at'] = $verified ? ($this->record->verified_at ?? now()) : null;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
