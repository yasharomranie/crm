<?php

namespace App\Filament\Resources\Events\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at')
            ->columns([
                TextColumn::make('title')
                    ->label('عنوان')
                    ->searchable(),
                TextColumn::make('starts_at')
                    ->label('تاریخ و ساعت')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('participants.name')
                    ->label('شرکت‌کنندگان')
                    ->badge()
                    ->listWithLineBreaks()
                    ->limitList(3),
                TextColumn::make('reminders_count')
                    ->label('تعداد یادآوری‌ها')
                    ->counts('reminders'),
                TextColumn::make('creator.name')
                    ->label('ایجادکننده')
                    ->toggleable(isToggledHiddenByDefault: true),
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
