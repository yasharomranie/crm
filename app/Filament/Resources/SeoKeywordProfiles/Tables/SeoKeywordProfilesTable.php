<?php

namespace App\Filament\Resources\SeoKeywordProfiles\Tables;

use App\Domain\Seo\UrlInspectionService;
use App\Enums\SeoCannibalizationRisk;
use App\Enums\SeoKeywordTrend;
use App\Enums\SeoSearchIntent;
use App\Exceptions\SeoGoogleException;
use App\Models\SeoKeywordProfile;
use App\Models\SeoProject;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SeoKeywordProfilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('impressions_28d', 'desc')
            ->columns([
                TextColumn::make('project.name')
                    ->label('پروژه')
                    ->searchable(),
                TextColumn::make('query')
                    ->label('کلمه کلیدی')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('current_position')
                    ->label('رتبه فعلی')
                    ->numeric(2)
                    ->sortable(),
                TextColumn::make('trend')
                    ->label('روند')
                    ->badge(),
                TextColumn::make('intent')
                    ->label('Intent')
                    ->badge()
                    ->placeholder('طبقه‌بندی‌نشده'),
                TextColumn::make('business_value')
                    ->label('ارزش تجاری')
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('topicCluster.name')
                    ->label('خوشه موضوعی')
                    ->placeholder('—'),
                TextColumn::make('clicks_28d')
                    ->label('کلیک (۲۸ روز)')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('impressions_28d')
                    ->label('ایمپرشن (۲۸ روز)')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('ctr_28d')
                    ->label('CTR')
                    ->formatStateUsing(fn ($state) => round($state * 100, 2).'٪')
                    ->sortable(),
                TextColumn::make('cannibalization_risk')
                    ->label('کانیبالیزیشن')
                    ->badge(),
                TextColumn::make('landing_page')
                    ->label('صفحه فرود')
                    ->limit(40)
                    ->placeholder('—')
                    ->url(fn ($record) => $record->landing_page, shouldOpenInNewTab: true),
                TextColumn::make('opportunity_score')
                    ->label('امتیاز فرصت')
                    ->numeric(2)
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('seo_project_id')
                    ->label('پروژه')
                    ->options(fn () => SeoProject::pluck('name', 'id')),
                SelectFilter::make('trend')
                    ->label('روند')
                    ->options(fn () => collect(SeoKeywordTrend::cases())->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])),
                SelectFilter::make('intent')
                    ->label('Intent')
                    ->options(fn () => collect(SeoSearchIntent::cases())->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])),
                SelectFilter::make('cannibalization_risk')
                    ->label('کانیبالیزیشن')
                    ->options(fn () => collect(SeoCannibalizationRisk::cases())->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])),
            ])
            ->recordActions([
                Action::make('inspectIndexing')
                    ->label('بررسی ایندکس')
                    ->icon(Heroicon::OutlinedMagnifyingGlass)
                    ->color('gray')
                    ->visible(fn (SeoKeywordProfile $record) => filled($record->landing_page)
                        && filled($record->project?->googleConnection?->gsc_site_url))
                    ->modalHeading('وضعیت ایندکس این صفحه در گوگل')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('بستن')
                    ->modalContent(function (SeoKeywordProfile $record) {
                        try {
                            $inspection = app(UrlInspectionService::class)->inspect($record->project, $record->landing_page);

                            return view('filament.seo.url-inspection-result', ['inspection' => $inspection]);
                        } catch (SeoGoogleException $exception) {
                            Notification::make()
                                ->title('بررسی ایندکس ناموفق بود')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();

                            return view('filament.seo.url-inspection-result', ['error' => $exception->getMessage()]);
                        }
                    }),
            ]);
    }
}
