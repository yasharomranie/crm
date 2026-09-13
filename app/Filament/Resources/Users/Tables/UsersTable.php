<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('نام و نام خانوادگی')->searchable(),
                TextColumn::make('mobile')->label('موبایل')->placeholder('—'),
                TextColumn::make('department.name')->label('دپارتمان')->placeholder('—'),
                TextColumn::make('workShift.name')->label('شیفت کاری')->placeholder('—'),
                TextColumn::make('roles.name')->label('نقش‌ها')->badge()->separator(','),
                IconColumn::make('is_active')->label('فعال')->boolean(),
            ])
            ->filters([
                SelectFilter::make('department_id')
                    ->label('دپارتمان')
                    ->relationship('department', 'name'),
                SelectFilter::make('work_shift_id')
                    ->label('شیفت کاری')
                    ->relationship('workShift', 'name'),
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
