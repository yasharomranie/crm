<?php

namespace App\Filament\Resources\SeoTechnicalAudits\Pages;

use App\Filament\Resources\SeoTechnicalAudits\SeoTechnicalAuditResource;
use App\Jobs\RunTechnicalSeoAuditJob;
use App\Models\SeoProject;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageSeoTechnicalAudits extends ManageRecords
{
    protected static string $resource = SeoTechnicalAuditResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('runAudit')
                ->label('اجرای بررسی جدید')
                ->icon(Heroicon::OutlinedPlayCircle)
                ->schema([
                    Select::make('seo_project_id')
                        ->label('پروژه')
                        ->options(fn () => SeoProject::where('is_active', true)->pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data) {
                    RunTechnicalSeoAuditJob::dispatch($data['seo_project_id']);

                    Notification::make()->title('بررسی فنی در صف اجرا قرار گرفت.')->success()->send();
                }),
        ];
    }
}
