<?php

namespace App\Filament\Resources\AiScheduledReports\Pages;

use App\Filament\Resources\AiScheduledReports\AiScheduledReportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Auth;

class ManageAiScheduledReports extends ManageRecords
{
    protected static string $resource = AiScheduledReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateDataUsing(fn (array $data): array => [...$data, 'user_id' => Auth::id()]),
        ];
    }
}
