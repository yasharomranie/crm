<?php

namespace App\Filament\Resources\Roles\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

class RoleTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('نام نقش')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('permissions_count')
                    ->label('تعداد مجوزها')
                    ->counts('permissions')
                    ->numeric()
                    ->badge(),
                TextColumn::make('users_count')
                    ->label('تعداد کاربران')
                    ->counts('users')
                    ->numeric()
                    ->badge()
                    ->color('gray'),
                TextColumn::make('created_at')
                    ->label('تاریخ ایجاد')
                    ->jalaliDateTime('Y/m/d')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalWidth('5xl'),
                DeleteAction::make()
                    ->disabled(fn (Role $record) => $record->name === 'admin')
                    ->tooltip(fn (Role $record) => $record->name === 'admin' ? 'نقش مدیر کل قابل حذف نیست' : null),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
