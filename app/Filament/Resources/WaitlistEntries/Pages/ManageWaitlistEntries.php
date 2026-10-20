<?php

namespace App\Filament\Resources\WaitlistEntries\Pages;

use App\Filament\Resources\WaitlistEntries\WaitlistEntryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageWaitlistEntries extends ManageRecords
{
    protected static string $resource = WaitlistEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->mutateDataUsing(function (array $data): array {
                $data['created_by'] = auth()->id();

                return $data;
            }),
        ];
    }
}
