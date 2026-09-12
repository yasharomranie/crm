<?php

namespace App\Filament\Resources\TaskChecklistTemplates\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TaskChecklistTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('نام قالب')->searchable(),
                TextColumn::make('department.name')->label('دپارتمان')->placeholder('—'),
                TextColumn::make('recurrence')->label('تکرار')->badge(),
                TextColumn::make('items_count')->label('تعداد آیتم')->counts('items'),
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
