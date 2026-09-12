<?php

namespace App\Filament\Resources\Deals\Tables;

use App\Enums\DealStage;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DealsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('عنوان')->searchable(),
                TextColumn::make('guest.name')->label('مشتری')->placeholder('—'),
                TextColumn::make('stage')->label('مرحله')->badge(),
                TextColumn::make('value')->label('ارزش')->numeric()->suffix(' تومان')->placeholder('—'),
                TextColumn::make('assignee.name')->label('کارشناس')->placeholder('—'),
                TextColumn::make('expected_close_date')->label('تخمین قطعی‌شدن')->date('Y/m/d')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('stage')->label('مرحله')->options(DealStage::class),
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
