<?php

namespace App\Filament\Resources\TicketVariants\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TicketVariantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')->label('محصول')->sortable(),
                TextColumn::make('name')->label('نام')->searchable(),
                TextColumn::make('direction')->label('جهت')->badge(),
                TextColumn::make('allowed_uses')->label('دفعات مجاز'),
                TextColumn::make('current_price_amount')
                    ->label('قیمت فعلی')
                    ->state(fn ($record) => $record->currentPrice()?->amount)
                    ->numeric()
                    ->placeholder('تعیین نشده'),
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
