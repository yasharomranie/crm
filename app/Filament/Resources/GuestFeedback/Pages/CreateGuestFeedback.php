<?php

namespace App\Filament\Resources\GuestFeedback\Pages;

use App\Filament\Resources\GuestFeedback\GuestFeedbackResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGuestFeedback extends CreateRecord
{
    protected static string $resource = GuestFeedbackResource::class;
}
