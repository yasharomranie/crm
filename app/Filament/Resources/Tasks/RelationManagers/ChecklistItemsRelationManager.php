<?php

namespace App\Filament\Resources\Tasks\RelationManagers;

use App\Models\TaskChecklistItem;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class ChecklistItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'checklistItems';

    protected static ?string $title = 'آیتم‌های چک‌لیست این تسک';

    protected static ?string $modelLabel = 'آیتم چک‌لیست';

    protected static ?string $pluralModelLabel = 'آیتم‌های چک‌لیست';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->columns([
                TextColumn::make('label')->label('عنوان آیتم'),
                ToggleColumn::make('is_checked')
                    ->label('انجام شد؟')
                    ->afterStateUpdated(function (TaskChecklistItem $record, bool $state): void {
                        $record->update([
                            'checked_by' => $state ? auth()->id() : null,
                            'checked_at' => $state ? now() : null,
                        ]);
                    }),
                TextColumn::make('checkedBy.name')->label('تیک‌زده توسط')->placeholder('—'),
                TextColumn::make('checked_at')->label('زمان انجام')->jalaliDateTime('Y/m/d H:i')->placeholder('—'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->recordActions([
                //
            ])
            ->toolbarActions([
                //
            ]);
    }
}
