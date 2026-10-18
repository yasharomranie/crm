<?php

namespace App\Filament\Resources\Deals\Tables;

use App\Domain\Crm\LeadScorer;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class DealsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('عنوان')->searchable(),
                TextColumn::make('guest.name')->label('مشتری')->placeholder('—'),
                TextColumn::make('stage')->label('مرحله')->badge(),
                TextColumn::make('lead_score')
                    ->label('دمای سرنخ')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (?int $state) => $state === null ? '—' : LeadScorer::labelFor($state).' ('.Number::toPersian($state).')')
                    ->color(fn (?int $state) => LeadScorer::colorFor($state))
                    ->tooltip(fn (Deal $record) => implode("\n", app(LeadScorer::class)->score($record)['reasons']) ?: null),
                TextColumn::make('value')->label('ارزش')->numeric()->suffix(' تومان')->placeholder('—'),
                TextColumn::make('assignee.name')->label('کارشناس')->placeholder('—'),
                TextColumn::make('expected_close_date')->label('تخمین قطعی‌شدن')->jalaliDate('Y/m/d')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('stage')->label('مرحله')->options(DealStage::class),
                SelectFilter::make('temperature')
                    ->label('دمای سرنخ')
                    ->options(['hot' => 'داغ', 'warm' => 'گرم', 'cold' => 'سرد'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'hot' => $query->where('lead_score', '>=', LeadScorer::HOT_AT),
                        'warm' => $query->whereBetween('lead_score', [LeadScorer::WARM_AT, LeadScorer::HOT_AT - 1]),
                        'cold' => $query->where('lead_score', '<', LeadScorer::WARM_AT),
                        default => $query,
                    }),
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
            'دمای سرنخ' => fn (Deal $deal) => LeadScorer::labelFor($deal->lead_score),
            'ارزش (تومان)' => fn (Deal $deal) => $deal->value ? Number::toPersian(number_format($deal->value)) : null,
            'کارشناس' => fn (Deal $deal) => $deal->assignee?->name,
            'منبع' => fn (Deal $deal) => $deal->source?->getLabel(),
            'تخمین قطعی‌شدن' => fn (Deal $deal) => $deal->expected_close_date?->toJalali('Y/m/d'),
        ];
    }
}
