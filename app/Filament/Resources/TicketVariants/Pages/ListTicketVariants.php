<?php

namespace App\Filament\Resources\TicketVariants\Pages;

use App\Filament\Resources\TicketVariants\TicketVariantResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTicketVariants extends ListRecords
{
    protected static string $resource = TicketVariantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
