<?php

namespace App\Filament\Resources\GuestTickets\Pages;

use App\Filament\Resources\GuestTickets\GuestTicketResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGuestTicket extends EditRecord
{
    protected static string $resource = GuestTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
