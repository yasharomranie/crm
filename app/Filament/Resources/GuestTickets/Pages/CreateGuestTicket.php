<?php

namespace App\Filament\Resources\GuestTickets\Pages;

use App\Filament\Resources\GuestTickets\GuestTicketResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGuestTicket extends CreateRecord
{
    protected static string $resource = GuestTicketResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}
