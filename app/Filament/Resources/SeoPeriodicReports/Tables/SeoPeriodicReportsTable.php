<?php

namespace App\Filament\Resources\SeoPeriodicReports\Tables;

use App\Models\SeoPeriodicReport;
use App\Models\SeoProject;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SeoPeriodicReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('period_start', 'desc')
            ->columns([
                TextColumn::make('project.name')
                    ->label('پروژه')
                    ->searchable(),
                TextColumn::make('period_start')
                    ->label('از تاریخ')
                    ->date('Y/m/d'),
                TextColumn::make('period_end')
                    ->label('تا تاریخ')
                    ->date('Y/m/d'),
                TextColumn::make('generated_at')
                    ->label('تاریخ تولید')
                    ->dateTime('Y/m/d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('seo_project_id')
                    ->label('پروژه')
                    ->options(fn () => SeoProject::pluck('name', 'id')),
            ])
            ->recordActions([
                Action::make('viewReport')
                    ->label('مشاهده / چاپ')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (SeoPeriodicReport $record) => route('seo-periodic-reports.print', $record))
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
