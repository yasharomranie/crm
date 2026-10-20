<?php

namespace App\Filament\Resources\BookingGroups\Pages;

use App\Filament\Resources\BookingGroups\Actions\BookingGroupActions;
use App\Filament\Resources\BookingGroups\BookingGroupResource;
use Filament\Resources\Pages\ViewRecord;

class ViewBookingGroup extends ViewRecord
{
    protected static string $resource = BookingGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            BookingGroupActions::pay(),
            BookingGroupActions::invoice(),
            BookingGroupActions::cancelAll(),
        ];
    }
}
