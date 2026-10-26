<?php

namespace App\Filament\Resources\DigitalProjects\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Read-only: the history is written by the project itself whenever its status changes. */
class StatusChangesRelationManager extends RelationManager
{
    protected static string $relationship = 'statusChanges';

    protected static ?string $title = 'تاریخچهٔ وضعیت';

    protected static ?string $modelLabel = 'تغییر وضعیت';

    protected static ?string $pluralModelLabel = 'تغییرهای وضعیت';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('to_status')
            ->columns([
                TextColumn::make('created_at')->label('زمان')->jalaliDate('Y/m/d H:i'),
                TextColumn::make('from_status')->label('از')->badge()->placeholder('—'),
                TextColumn::make('to_status')->label('به')->badge(),
                TextColumn::make('note')->label('دلیل')->placeholder('—')->wrap(),
                TextColumn::make('author.name')->label('تغییردهنده')->placeholder('—'),
            ])
            ->defaultSort('id', 'desc');
    }
}
