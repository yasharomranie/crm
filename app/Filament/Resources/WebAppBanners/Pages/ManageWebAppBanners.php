<?php

namespace App\Filament\Resources\WebAppBanners\Pages;

use App\Filament\Resources\WebAppBanners\WebAppBannerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageWebAppBanners extends ManageRecords
{
    protected static string $resource = WebAppBannerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
