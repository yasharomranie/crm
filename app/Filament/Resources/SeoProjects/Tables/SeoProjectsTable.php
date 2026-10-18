<?php

namespace App\Filament\Resources\SeoProjects\Tables;

use App\Enums\SeoConnectionStatus;
use App\Models\SeoWpSite;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SeoProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('پروژه')
                    ->searchable()
                    ->description(fn ($record) => $record->website_url),
                TextColumn::make('goal')
                    ->label('هدف'),
                TextColumn::make('googleConnection.status')
                    ->label('اتصال Search Console')
                    ->badge()
                    ->default(SeoConnectionStatus::NotConnected)
                    ->formatStateUsing(fn ($state) => $state instanceof SeoConnectionStatus ? $state->getLabel() : SeoConnectionStatus::NotConnected->getLabel())
                    ->color(fn ($state) => ($state instanceof SeoConnectionStatus ? $state : SeoConnectionStatus::NotConnected)->getColor()),
                TextColumn::make('googleConnection.last_synced_at')
                    ->label('آخرین همگام‌سازی')
                    ->dateTime('Y/m/d H:i')
                    ->placeholder('—'),
                TextColumn::make('wpSite.status')
                    ->label('وردپرس')
                    ->badge()
                    ->state(fn ($record) => match (true) {
                        ! $record->wpSite => null,
                        $record->wpSite->isStale() => 'قدیمی',
                        default => match ($record->wpSite->status) {
                            SeoWpSite::STATUS_CONNECTED => 'متصل',
                            SeoWpSite::STATUS_DISCONNECTED => 'قطع‌شده',
                            default => 'در انتظار اتصال',
                        },
                    })
                    ->color(fn (?string $state) => match ($state) {
                        'متصل' => 'success',
                        'قدیمی' => 'warning',
                        'قطع‌شده' => 'danger',
                        default => 'gray',
                    })
                    ->placeholder('—'),
                IconColumn::make('is_active')
                    ->label('فعال')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
