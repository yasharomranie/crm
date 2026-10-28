<?php

namespace App\Filament\Resources\GuideObjects\Pages;

use App\Filament\Resources\GuideObjects\GuideObjectResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGuideObjects extends ListRecords
{
    protected static string $resource = GuideObjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('enroll')
                ->label('پردازش تصاویر مرجع')
                ->icon('heroicon-o-cpu-chip')
                ->color('gray')
                ->url(route('guide.enroll'))
                ->visible(fn () => auth()->user()?->can('guide-reference-images.update') ?? false),
            Action::make('accuracy')
                ->label('آزمون دقت')
                ->icon('heroicon-o-beaker')
                ->color('gray')
                ->url(route('guide.accuracy')),
            Action::make('scanner')
                ->label('باز کردن اسکنر')
                ->icon('heroicon-o-camera')
                ->color('gray')
                ->url(route('guide.scanner')),
            CreateAction::make(),
        ];
    }
}
