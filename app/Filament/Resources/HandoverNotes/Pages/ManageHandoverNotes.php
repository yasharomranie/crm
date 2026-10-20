<?php

namespace App\Filament\Resources\HandoverNotes\Pages;

use App\Filament\Resources\HandoverNotes\HandoverNoteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageHandoverNotes extends ManageRecords
{
    protected static string $resource = HandoverNoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->mutateDataUsing(fn (array $data): array => $data + ['created_by' => auth()->id()]),
        ];
    }
}
