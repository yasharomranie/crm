<?php

namespace App\Filament\Resources\SeoProjects\Pages;

use App\Filament\Resources\SeoProjects\SeoProjectResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListSeoProjects extends ListRecords
{
    protected static string $resource = SeoProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadWpPlugin')
                ->label('دانلود پلاگین وردپرس')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->tooltip('فایل zip پلاگین اتصال؛ از بخش افزونه‌های وردپرس نصبش کنید. بعد از نصب، خودش به‌روز می‌شود.')
                ->url(fn () => route('seo.wp-plugin.download')),
            CreateAction::make(),
        ];
    }
}
