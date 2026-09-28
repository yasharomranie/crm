<?php

namespace App\Filament\Resources\WebAppSections\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WebAppSectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('title')
                    ->label('سرتیتر')
                    ->searchable(),
                TextColumn::make('items_count')
                    ->label('تعداد آیتم')
                    ->counts('items'),
                TextColumn::make('sort_order')
                    ->label('ترتیب')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('فعال')
                    ->boolean(),
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
