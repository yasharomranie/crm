<?php

namespace App\Filament\Resources\Deals\Tables;

use App\Enums\DealStage;
use App\Filament\Support\TableExports;
use App\Models\Deal;
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
                TextColumn::make('expected_close_date')->label('تخمین قطعی‌شدن')->jalaliDate('Y/m/d')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('stage')->label('مرحله')->options(DealStage::class),
            ])
            ->headerActions([
                TableExports::excel('معاملات-فروش', static::exportColumns()),
                TableExports::pdf('گزارش معاملات فروش', static::exportColumns()),
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

    /**
     * @return array<string, \Closure(Deal): mixed>
     */
    private static function exportColumns(): array
    {
        return [
            'عنوان' => fn (Deal $deal) => $deal->title,
            'مشتری' => fn (Deal $deal) => $deal->guest?->name,
            'مرحله' => fn (Deal $deal) => $deal->stage->getLabel(),
            'ارزش (تومان)' => fn (Deal $deal) => $deal->value ? number_format($deal->value) : null,
            'کارشناس' => fn (Deal $deal) => $deal->assignee?->name,
            'منبع' => fn (Deal $deal) => $deal->source?->getLabel(),
            'تخمین قطعی‌شدن' => fn (Deal $deal) => $deal->expected_close_date?->toJalali('Y/m/d'),
        ];
    }
}
