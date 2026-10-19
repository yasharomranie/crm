<?php

namespace App\Filament\Resources\SeoWpPosts\Tables;

use App\Domain\Seo\UrlInspectionService;
use App\Domain\Seo\WpConnector\WpPostIssues;
use App\Exceptions\SeoGoogleException;
use App\Models\SeoProject;
use App\Models\SeoWpPost;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SeoWpPostsTable
{
    private const STATUS_LABELS = [
        'publish' => 'منتشرشده',
        'draft' => 'پیش‌نویس',
        'pending' => 'در انتظار بررسی',
        'future' => 'زمان‌بندی‌شده',
        'private' => 'خصوصی',
    ];

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('site.project'))
            ->defaultSort('modified_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('عنوان')
                    ->searchable()
                    ->limit(60)
                    ->wrap()
                    ->url(fn (SeoWpPost $record) => $record->url, shouldOpenInNewTab: true),
                TextColumn::make('site.project.name')
                    ->label('پروژه')
                    ->toggleable(),
                TextColumn::make('issues')
                    ->label('مشکلات')
                    ->badge()
                    ->state(fn (SeoWpPost $record) => $record->issues ?? [])
                    ->formatStateUsing(fn (string $state) => WpPostIssues::label($state))
                    ->color(fn (string $state) => WpPostIssues::color($state))
                    ->placeholder('—'),
                TextColumn::make('gsc_clicks')
                    ->label('کلیک (۲۸ روز)')
                    ->numeric()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('gsc_impressions')
                    ->label('ایمپرشن')
                    ->numeric()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('gsc_position')
                    ->label('رتبه')
                    ->numeric(1)
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('ga_sessions')
                    ->label('نشست (GA، ۲۸ روز)')
                    ->numeric()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('ga_key_events')
                    ->label('تبدیل (GA)')
                    ->numeric()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('focus_keyword')
                    ->label('کلمه کانونی')
                    ->state(fn (SeoWpPost $record) => $record->seo['focus_keyword'] ?? null)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('inbound_links')
                    ->label('لینک ورودی')
                    ->numeric()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('word_count')
                    ->label('کلمات')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('post_type')
                    ->label('نوع')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => self::STATUS_LABELS[$state] ?? $state)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('modified_at')
                    ->label('آخرین ویرایش')
                    ->jalaliDateTime('Y/m/d H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('project')
                    ->label('پروژه')
                    ->options(fn () => SeoProject::query()->whereHas('wpSite')->pluck('name', 'id'))
                    ->query(fn (Builder $query, array $data) => filled($data['value'])
                        ? $query->whereHas('site', fn (Builder $site) => $site->where('seo_project_id', $data['value']))
                        : $query),
                SelectFilter::make('issue')
                    ->label('مشکل')
                    ->options(fn () => collect(WpPostIssues::ISSUES)->map(fn (array $issue) => $issue['label'])->all())
                    ->query(fn (Builder $query, array $data) => filled($data['value'])
                        ? $query->whereJsonContains('issues', $data['value'])
                        : $query),
                Filter::make('has_issues')
                    ->label('فقط صفحه‌های دارای مشکل')
                    ->query(fn (Builder $query) => $query->whereJsonLength('issues', '>', 0)),
                SelectFilter::make('post_type')
                    ->label('نوع')
                    ->options(fn () => SeoWpPost::query()->distinct()->pluck('post_type', 'post_type')),
                SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options(self::STATUS_LABELS),
            ])
            ->recordActions([
                Action::make('inspectIndexing')
                    ->label('بررسی ایندکس')
                    ->icon(Heroicon::OutlinedMagnifyingGlass)
                    ->color('gray')
                    ->visible(fn (SeoWpPost $record) => $record->status === 'publish'
                        && filled($record->site?->project?->googleConnection?->gsc_site_url))
                    ->modalHeading('وضعیت ایندکس این صفحه در گوگل')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('بستن')
                    ->modalContent(function (SeoWpPost $record) {
                        try {
                            $inspection = app(UrlInspectionService::class)->inspect($record->site->project, $record->url);

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
