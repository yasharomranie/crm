<?php

namespace App\Filament\Resources\Rooms\Tables;

use App\Enums\RoomStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RoomsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('واحد')->searchable(),
                TextColumn::make('roomType.name')->label('نوع'),
                TextColumn::make('department.name')->label('دپارتمان')->placeholder('—'),
                TextColumn::make('status')->label('وضعیت')->badge(),
                IconColumn::make('is_active')->label('فعال')->boolean(),
            ])
            ->filters([
                SelectFilter::make('status')->label('وضعیت')->options(RoomStatus::class),
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
