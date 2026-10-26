<?php

namespace App\Filament\Resources\DigitalProjects\Pages;

use App\Filament\Resources\DigitalProjects\DigitalProjectResource;
use App\Filament\Widgets\DigitalProjectQuickLogWidget;
use App\Filament\Widgets\DigitalProjectsStatsWidget;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\On;

class ListDigitalProjects extends ListRecords
{
    protected static string $resource = DigitalProjectResource::class;

    /** Re-renders the table (and its "latest report" column) after a quick entry. */
    #[On('digital-project-log-saved')]
    public function refreshAfterQuickLog(): void {}

    protected function getHeaderActions(): array
    {
        return [
            Action::make('weeklyReport')
                ->label('گزارش هفتگی (چاپ)')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->url(fn (): string => route('digital-projects.weekly-report'), shouldOpenInNewTab: true),
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
            DigitalProjectQuickLogWidget::class,
        ];
    }
}
