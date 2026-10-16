<?php

namespace App\Filament\Resources\Guests\Pages;

use App\Enums\MessageChannel;
use App\Filament\Actions\LogCrmActivityAction;
use App\Filament\Actions\MergeGuestIntoAnotherAction;
use App\Filament\Actions\SendGuestMessageAction;
use App\Filament\Resources\Guests\GuestResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewGuest extends ViewRecord
{
    protected static string $resource = GuestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LogCrmActivityAction::make(),
            SendGuestMessageAction::make(MessageChannel::Email),
            SendGuestMessageAction::make(MessageChannel::Sms),
            MergeGuestIntoAnotherAction::make(),
            EditAction::make(),
        ];
    }
}
