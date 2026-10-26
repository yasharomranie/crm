<?php

namespace App\Filament\Resources\DigitalProjectLogs\Pages;

use App\Filament\Resources\DigitalProjectLogs\DigitalProjectLogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDigitalProjectLogs extends ManageRecords
{
    protected static string $resource = DigitalProjectLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->mutateDataUsing(fn (array $data): array => $data + ['user_id' => auth()->id()]),
        ];
    }
}
