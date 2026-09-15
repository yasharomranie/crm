<?php

namespace App\Filament\Resources\GuestFeedback\Tables;

use App\Filament\Support\TableExports;
use App\Models\GuestFeedback;
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
                TextColumn::make('nps_score')->label('امتیاز NPS')->numeric()->placeholder('در انتظار پاسخ'),
                TextColumn::make('comment')->label('نظر')->limit(50)->placeholder('—'),
            ])
            ->defaultSort('requested_at', 'desc')
            ->filters([
                //
            ])
            ->headerActions([
                TableExports::excel('بازخوردهای-مهمانان', static::exportColumns()),
                TableExports::pdf('گزارش بازخورد و NPS مهمانان', static::exportColumns()),
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
     * @return array<string, \Closure(GuestFeedback): mixed>
     */
    private static function exportColumns(): array
    {
        return [
            'مشتری' => fn (GuestFeedback $feedback) => $feedback->guest?->name,
            'تاریخ درخواست' => fn (GuestFeedback $feedback) => $feedback->requested_at?->toJalali('Y/m/d'),
            'امتیاز NPS' => fn (GuestFeedback $feedback) => $feedback->nps_score,
            'نظر' => fn (GuestFeedback $feedback) => $feedback->comment,
        ];
    }
}
