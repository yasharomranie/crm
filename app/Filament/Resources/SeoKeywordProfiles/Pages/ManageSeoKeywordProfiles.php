<?php

namespace App\Filament\Resources\SeoKeywordProfiles\Pages;

use App\Filament\Resources\SeoKeywordProfiles\SeoKeywordProfileResource;
use App\Filament\Widgets\SeoKeywordProfileStatsWidget;
use Filament\Resources\Pages\ManageRecords;

class ManageSeoKeywordProfiles extends ManageRecords
{
    protected static string $resource = SeoKeywordProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @return array<class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            SeoKeywordProfileStatsWidget::class,
        ];
    }
}
