<?php

namespace App\Filament\Resources\GuestFeedback\Pages;

use App\Filament\Resources\GuestFeedback\GuestFeedbackResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGuestFeedback extends EditRecord
{
    protected static string $resource = GuestFeedbackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
