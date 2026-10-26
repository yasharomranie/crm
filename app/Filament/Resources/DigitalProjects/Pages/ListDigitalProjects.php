<?php

namespace App\Filament\Resources\DigitalProjects\Pages;

use App\Filament\Resources\DigitalProjects\DigitalProjectResource;
use App\Filament\Widgets\DigitalProjectsStatsWidget;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDigitalProjects extends ListRecords
{
    protected static string $resource = DigitalProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * @return array<class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            DigitalProjectsStatsWidget::class,
        ];
    }
}
