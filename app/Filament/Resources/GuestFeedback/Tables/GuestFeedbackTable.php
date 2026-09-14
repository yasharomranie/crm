<?php

namespace App\Filament\Resources\GuestFeedback\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GuestFeedbackTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('guest.name')->label('مشتری')->searchable(),
                TextColumn::make('requested_at')->label('تاریخ درخواست')->jalaliDateTime('Y/m/d')->placeholder('—'),
                TextColumn::make('nps_score')->label('امتیاز NPS')->placeholder('در انتظار پاسخ'),
                TextColumn::make('comment')->label('نظر')->limit(50)->placeholder('—'),
            ])
            ->defaultSort('requested_at', 'desc')
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
