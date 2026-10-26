<?php

namespace App\Filament\Resources\DigitalProjects\Pages;

use App\Filament\Resources\DigitalProjects\DigitalProjectResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDigitalProject extends EditRecord
{
    protected static string $resource = DigitalProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
