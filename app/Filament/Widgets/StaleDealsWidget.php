<?php

namespace App\Filament\Widgets;

use App\Enums\DealStage;
use App\Filament\Support\TableExports;
use App\Models\Deal;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Number;

/**
 * Open deals nobody has touched in a while - the "needs attention" list
 * every CRM report has, so stalled deals surface on the pipeline page
 * itself instead of quietly aging in a kanban column.
 */
class StaleDealsWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('معاملات راکد')
            ->description('معاملات باز که بیش از ۱۴ روز است تغییری نکرده‌اند و نیاز به پیگیری دارند')
            ->query(
                Deal::query()
                    ->whereNotIn('stage', [DealStage::Won, DealStage::Lost])
                    ->where('updated_at', '<=', now()->subDays(14))
                    ->with(['guest', 'assignee'])
            )
            ->defaultSort('updated_at')
            ->headerActions([
                TableExports::excel('معاملات-راکد', static::exportColumns()),
                TableExports::pdf('گزارش معاملات راکد', static::exportColumns()),
            ])
            ->columns([
                TextColumn::make('title')->label('عنوان'),
                TextColumn::make('guest.name')->label('مشتری')->placeholder('—'),
                TextColumn::make('stage')->label('مرحله')->badge(),
                TextColumn::make('assignee.name')->label('کارشناس')->placeholder('بدون کارشناس'),
                TextColumn::make('value')->label('ارزش')->numeric()->suffix(' تومان')->placeholder('—'),
                TextColumn::make('updated_at')->label('آخرین به‌روزرسانی')->jalaliDate('Y/m/d')->sortable(),
                TextColumn::make('updated_at')
                    ->label('روز بدون پیگیری')
                    ->state(fn (Deal $record) => (int) $record->updated_at->diffInDays(now()))
                    ->formatStateUsing(fn (int $state) => Number::toPersian("{$state} روز"))
                    ->badge()
                    ->color('danger'),
            ])
            ->recordActions([
                EditAction::make()->url(fn (Deal $record) => route('filament.admin.resources.deals.edit', $record)),
            ])
            ->emptyStateHeading('هیچ معاملهٔ راکدی نیست')
            ->emptyStateDescription('همهٔ معاملات باز اخیراً به‌روزرسانی شده‌اند.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([5, 10, 25]);
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
            'کارشناس' => fn (Deal $deal) => $deal->assignee?->name,
            'ارزش (تومان)' => fn (Deal $deal) => $deal->value ? Number::toPersian(number_format($deal->value)) : null,
            'آخرین به‌روزرسانی' => fn (Deal $deal) => $deal->updated_at->toJalali('Y/m/d'),
            'روز بدون پیگیری' => fn (Deal $deal) => Number::toPersian((int) $deal->updated_at->diffInDays(now())),
        ];
    }
}
