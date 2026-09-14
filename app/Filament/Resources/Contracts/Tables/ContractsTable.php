<?php

namespace App\Filament\Resources\Contracts\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContractsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('عنوان')->searchable()->weight('bold'),
                TextColumn::make('category.name')->label('دسته')->placeholder('—')->badge(),
                TextColumn::make('company.name')->label('کارفرما')->placeholder('—'),
                TextColumn::make('counterparty_name')->label('طرف مقابل')->searchable(),
                TextColumn::make('creator.name')->label('تهیه‌کننده')->placeholder('—'),
                TextColumn::make('created_at')->label('تاریخ ایجاد')->jalaliDateTime('Y/m/d H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('print')
                    ->label('چاپ')
                    ->icon('heroicon-o-printer')
                    ->url(fn ($record) => route('contracts.print', $record))
                    ->openUrlInNewTab(),
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
