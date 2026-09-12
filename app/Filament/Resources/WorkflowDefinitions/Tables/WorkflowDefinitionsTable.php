<?php

namespace App\Filament\Resources\WorkflowDefinitions\Tables;

use App\Models\FormSubmission;
use App\Models\Task;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WorkflowDefinitionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('نام گردش‌کار')->searchable(),
                TextColumn::make('subject_type')->label('اعمال روی')->formatStateUsing(fn (string $state) => match ($state) {
                    FormSubmission::class => 'ثبت فرم',
                    Task::class => 'تسک',
                    default => $state,
                }),
                TextColumn::make('steps_count')->label('تعداد مراحل')->counts('steps'),
                IconColumn::make('is_active')->label('فعال')->boolean(),
            ])
            ->filters([
                //
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
