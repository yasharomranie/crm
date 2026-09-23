<?php

namespace App\Filament\Resources\TicketVariants\Pages;

use App\Filament\Resources\TicketVariants\TicketVariantResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTicketVariant extends EditRecord
{
    protected static string $resource = TicketVariantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
