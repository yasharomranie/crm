<?php

namespace App\Filament\Resources\Documents\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Read-only history of a document's past files. Versions are created only
 * automatically, by Document::replaceFile() when an edit uploads a new
 * file - there's no manual "create version" action here.
 */
class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    protected static ?string $title = 'تاریخچهٔ نسخه‌ها';

    protected static ?string $modelLabel = 'نسخه';

    protected static ?string $pluralModelLabel = 'نسخه‌ها';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('version_number')->label('نسخه')->prefix('نسخهٔ '),
                TextColumn::make('size_kb')->label('حجم')->formatStateUsing(fn (?int $state) => $state ? number_format($state).' کیلوبایت' : '—'),
                TextColumn::make('uploader.name')->label('بارگذاری‌کننده'),
                TextColumn::make('created_at')->label('تاریخ بایگانی')->jalaliDateTime('Y/m/d H:i'),
            ])
            ->headerActions([
                //
            ])
            ->recordActions([
                Action::make('download')
                    ->label('دانلود')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->url(fn ($record) => route('document-versions.download', $record))
                    ->openUrlInNewTab(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
