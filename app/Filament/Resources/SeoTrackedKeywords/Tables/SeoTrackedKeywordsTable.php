<?php

namespace App\Filament\Resources\SeoTrackedKeywords\Tables;

use App\Jobs\CheckKeywordRankJob;
use App\Models\SeoProject;
use App\Models\SeoTrackedKeyword;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class SeoTrackedKeywordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('project.name')
                    ->label('پروژه')
                    ->searchable(),
                TextColumn::make('keyword')
                    ->label('کلمه کلیدی')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('latestRankCheck.position')
                    ->label('رتبه‌ی امروز')
                    ->badge()
                    ->color(fn (?int $state) => $state === null ? 'gray' : ($state <= 10 ? 'success' : 'warning'))
                    ->formatStateUsing(fn (?int $state) => $state ?? 'خارج از ۱۰ نتیجه‌ی اول')
                    ->placeholder('هنوز چک نشده'),
                TextColumn::make('latestRankCheck.date')
                    ->label('آخرین بررسی')
                    ->date('Y/m/d')
                    ->placeholder('—'),
                IconColumn::make('is_active')
                    ->label('فعال')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('seo_project_id')
                    ->label('پروژه')
                    ->options(fn () => SeoProject::pluck('name', 'id')),
                TernaryFilter::make('is_active')
                    ->label('وضعیت')
                    ->default(true),
            ])
            ->recordActions([
                Action::make('checkNow')
                    ->label('بررسی الان')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('info')
                    ->action(function (SeoTrackedKeyword $record) {
                        CheckKeywordRankJob::dispatch($record->id);

                        Notification::make()->title('بررسی رتبه در صف اجرا قرار گرفت.')->success()->send();
                    }),
                Action::make('viewHistory')
                    ->label('مشاهده روند')
                    ->icon(Heroicon::OutlinedChartBar)
                    ->color('gray')
                    ->modalHeading(fn (SeoTrackedKeyword $record) => $record->keyword)
                    ->modalContent(fn (SeoTrackedKeyword $record) => view('filament.seo.tracked-keyword-history', [
                        'checks' => $record->rankChecks()->orderByDesc('date')->limit(30)->get(),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('بستن'),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
