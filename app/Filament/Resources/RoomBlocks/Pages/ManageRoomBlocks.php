<?php

namespace App\Filament\Resources\RoomBlocks\Pages;

use App\Filament\Resources\RoomBlocks\RoomBlockResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRoomBlocks extends ManageRecords
{
    protected static string $resource = RoomBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->mutateDataUsing(fn (array $data): array => $data + ['created_by' => auth()->id()]),
        ];
    }
}
