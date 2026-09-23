<?php

namespace App\Filament\Resources\DiscountCodes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DiscountCodesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('کد')->searchable(),
                TextColumn::make('discount_type')->label('نوع'),
                TextColumn::make('discount_value')->label('مقدار'),
                TextColumn::make('used_count')->label('استفاده‌شده'),
                TextColumn::make('usage_limit')->label('سقف کل')->placeholder('نامحدود'),
                IconColumn::make('is_active')->label('فعال')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
