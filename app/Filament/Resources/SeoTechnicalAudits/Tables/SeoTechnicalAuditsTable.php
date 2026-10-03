<?php

namespace App\Filament\Resources\SeoTechnicalAudits\Tables;

use App\Enums\SeoTechnicalAuditStatus;
use App\Enums\SeoTechnicalIssueSeverity;
use App\Models\SeoProject;
use App\Models\SeoTechnicalAudit;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SeoTechnicalAuditsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('started_at', 'desc')
            ->columns([
                TextColumn::make('project.name')
                    ->label('پروژه')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge(),
                TextColumn::make('score')
                    ->label('امتیاز')
                    ->badge()
                    ->color(fn (?int $state) => match (true) {
                        $state === null => 'gray',
                        $state >= 80 => 'success',
                        $state >= 50 => 'warning',
                        default => 'danger',
                    })
                    ->placeholder('—'),
                TextColumn::make('performance_score')
                    ->label('امتیاز سرعت (موبایل)')
                    ->badge()
                    ->color(fn (?int $state) => match (true) {
                        $state === null => 'gray',
                        $state >= 90 => 'success',
                        $state >= 50 => 'warning',
                        default => 'danger',
                    })
                    ->placeholder('—'),
                TextColumn::make('issues_count')
                    ->label('تعداد مشکلات')
                    ->counts('issues'),
                TextColumn::make('pages_scanned')
                    ->label('صفحات بررسی‌شده'),
                TextColumn::make('started_at')
                    ->label('شروع')
                    ->dateTime('Y/m/d H:i')
                    ->sortable(),
                TextColumn::make('completed_at')
                    ->label('پایان')
                    ->dateTime('Y/m/d H:i')
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('seo_project_id')
                    ->label('پروژه')
                    ->options(fn () => SeoProject::pluck('name', 'id')),
                SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options(fn () => collect(SeoTechnicalAuditStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])),
            ])
            ->recordActions([
                Action::make('viewIssues')
                    ->label('مشاهده مشکلات')
                    ->icon(Heroicon::OutlinedListBullet)
                    ->color('gray')
                    ->modalHeading(fn (SeoTechnicalAudit $record) => 'مشکلات یافت‌شده — '.$record->project->name)
                    ->modalContent(fn (SeoTechnicalAudit $record) => view('filament.seo.technical-audit-issues', [
                        'audit' => $record,
                        'issues' => $record->issues()->get()->sortBy(fn ($issue) => match ($issue->severity) {
                            SeoTechnicalIssueSeverity::Critical => 0,
                            SeoTechnicalIssueSeverity::Warning => 1,
                            SeoTechnicalIssueSeverity::Info => 2,
                        }),
                        'weakestLinkedPages' => $record->internalLinks()
                            ->selectRaw('to_url, count(distinct from_url) as incoming')
                            ->groupBy('to_url')
                            ->havingRaw('count(distinct from_url) <= 1')
                            ->orderBy('incoming')
                            ->limit(10)
                            ->get(),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('بستن'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
