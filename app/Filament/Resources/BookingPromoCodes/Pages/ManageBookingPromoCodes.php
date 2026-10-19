<?php

namespace App\Filament\Resources\BookingPromoCodes\Pages;

use App\Filament\Resources\BookingPromoCodes\BookingPromoCodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBookingPromoCodes extends ManageRecords
{
    protected static string $resource = BookingPromoCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
