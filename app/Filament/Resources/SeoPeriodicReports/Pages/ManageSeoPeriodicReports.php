<?php

namespace App\Filament\Resources\SeoPeriodicReports\Pages;

use App\Domain\Seo\PeriodicReportService;
use App\Filament\Resources\SeoPeriodicReports\SeoPeriodicReportResource;
use App\Models\SeoProject;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

class ManageSeoPeriodicReports extends ManageRecords
{
    protected static string $resource = SeoPeriodicReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateReport')
                ->label('تولید گزارش')
                ->icon(Heroicon::OutlinedDocumentPlus)
                ->schema([
                    Select::make('seo_project_id')
                        ->label('پروژه')
                        ->options(fn () => SeoProject::where('is_active', true)->pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                    DatePicker::make('period_start')
                        ->label('ابتدای بازه')
                        ->jalali()
                        ->required()
                        ->default(now()->subMonthNoOverflow()->startOfMonth()),
                    DatePicker::make('period_end')
                        ->label('انتهای بازه')
                        ->jalali()
                        ->required()
                        ->default(now()->subMonthNoOverflow()->endOfMonth()),
                ])
                ->action(function (array $data, PeriodicReportService $service) {
                    $service->generate(
                        SeoProject::findOrFail($data['seo_project_id']),
                        Carbon::parse($data['period_start']),
                        Carbon::parse($data['period_end']),
                    );

                    Notification::make()->title('گزارش دوره‌ای ساخته شد.')->success()->send();
                }),
        ];
    }
}
