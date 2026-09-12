<?php

namespace App\Filament\Resources\GuestTickets\Pages;

use App\Filament\Resources\GuestTickets\GuestTicketResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGuestTickets extends ListRecords
{
    protected static string $resource = GuestTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
