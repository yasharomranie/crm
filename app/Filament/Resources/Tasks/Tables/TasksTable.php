<?php

namespace App\Filament\Resources\Tasks\Tables;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TasksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('عنوان')->searchable(),
                TextColumn::make('department.name')->label('دپارتمان')->placeholder('—'),
                TextColumn::make('assignee.name')->label('مسئول')->placeholder('—'),
                TextColumn::make('priority')->label('اولویت')->badge(),
                TextColumn::make('status')->label('وضعیت')->badge(),
                TextColumn::make('due_at')->label('مهلت')->dateTime('Y/m/d H:i')->placeholder('—'),
                TextColumn::make('tags.name')->label('برچسب‌ها')->badge()->separator(','),
            ])
            ->defaultSort('due_at')
            ->filters([
                SelectFilter::make('status')->label('وضعیت')->options(TaskStatus::class),
                SelectFilter::make('priority')->label('اولویت')->options(TaskPriority::class),
            ])
            ->recordActions([
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
