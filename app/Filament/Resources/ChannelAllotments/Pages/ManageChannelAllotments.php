<?php

namespace App\Filament\Resources\ChannelAllotments\Pages;

use App\Filament\Resources\ChannelAllotments\ChannelAllotmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageChannelAllotments extends ManageRecords
{
    protected static string $resource = ChannelAllotmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
