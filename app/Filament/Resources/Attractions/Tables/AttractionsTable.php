<?php

namespace App\Filament\Resources\Attractions\Tables;

use App\Enums\AttractionCategory;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class AttractionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('image')
                    ->label('تصویر')
                    ->disk('public')
                    ->circular(),
                TextColumn::make('name')
                    ->label('نام')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category')
                    ->label('دسته')
                    ->badge(),
                TextColumn::make('avg_visit_minutes')
                    ->label('مدت بازدید')
                    ->suffix(' دقیقه'),
                TextColumn::make('boost_weight')
                    ->label('اولویت')
                    ->badge()
                    ->color(fn (int $state) => match (true) {
                        $state > 0 => 'success',
                        $state < 0 => 'gray',
                        default => 'info',
                    }),
                TextColumn::make('closed_until')
                    ->label('تعطیل تا')
                    ->jalaliDate('Y/m/d')
                    ->placeholder('—'),
                ToggleColumn::make('is_active')
                    ->label('فعال'),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label('دسته')
                    ->options(AttractionCategory::class),
                TernaryFilter::make('is_active')
                    ->label('فعال'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
