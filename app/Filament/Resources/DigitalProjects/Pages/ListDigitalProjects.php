<?php

namespace App\Filament\Resources\DigitalProjects\Pages;

use App\Domain\DigitalProjects\DigitalProjectAiSummary;
use App\Exceptions\AiProviderException;
use App\Filament\Resources\DigitalProjects\DigitalProjectResource;
use App\Filament\Widgets\DigitalProjectQuickLogWidget;
use App\Filament\Widgets\DigitalProjectsStatsWidget;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
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
            Action::make('aiSummary')
                ->label('خلاصهٔ هوشمند هفته')
                ->icon(Heroicon::OutlinedSparkles)
                ->color('gray')
                ->modalHeading('خلاصهٔ هوشمند کار ۷ روز گذشته')
                ->modalDescription('با هوش مصنوعی از گزارش‌ها، اقدام‌ها و آمار نوشته می‌شود. فقط وقتی این پنجره را باز کنید توکن مصرف می‌شود و نتیجه نیم‌ساعت نگه داشته می‌شود.')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('بستن')
                ->modalContent(function () {
                    try {
                        $text = Cache::get('digital-projects-ai-summary');

                        if ($text === null) {
                            $text = app(DigitalProjectAiSummary::class)->summarize();

                            if ($text !== '') {
                                Cache::put('digital-projects-ai-summary', $text, now()->addMinutes(30));
                            }
                        }

                        return view('digital-projects.ai-summary', ['text' => $text]);
                    } catch (AiProviderException $exception) {
                        return view('digital-projects.ai-summary', ['error' => $exception->getMessage()]);
                    }
                }),
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
