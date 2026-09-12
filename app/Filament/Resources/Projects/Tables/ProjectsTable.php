<?php

namespace App\Filament\Resources\Projects\Tables;

use App\Enums\ProjectStatus;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Project;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('کد پروژه')->fontFamily('mono')->searchable(),
                TextColumn::make('title')->label('عنوان')->searchable(),
                TextColumn::make('department.name')->label('دپارتمان')->placeholder('—'),
                TextColumn::make('owner.name')->label('متولی اجرایی')->placeholder('—'),
                TextColumn::make('status')->label('وضعیت')->badge(),
                TextColumn::make('budget')->label('بودجه')->numeric()->suffix(' تومان'),
                TextColumn::make('progress')
                    ->label('پیشرفت فیزیکی')
                    ->state(fn (Project $record) => round($record->earnedValuePercent()).'٪'),
                TextColumn::make('end_date')->label('پایان مصوب')->date('Y/m/d')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->label('وضعیت')->options(ProjectStatus::class),
            ])
            ->recordActions([
                Action::make('dashboard')
                    ->label('گانت و EVM')
                    ->icon(Heroicon::OutlinedChartBar)
                    ->color('gray')
                    ->url(fn (Project $record) => ProjectResource::getUrl('view', ['record' => $record])),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
