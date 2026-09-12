<?php

namespace App\Filament\Resources\GuestFeedback\Pages;

use App\Filament\Resources\GuestFeedback\GuestFeedbackResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGuestFeedback extends ListRecords
{
    protected static string $resource = GuestFeedbackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
