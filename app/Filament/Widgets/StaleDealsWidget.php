<?php

namespace App\Filament\Widgets;

use App\Enums\DealStage;
use App\Filament\Widgets\Concerns\OnlyVisibleOnSalesPipeline;
use App\Models\Deal;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Open deals nobody has touched in a while - the "needs attention" list
 * every CRM report has, so stalled deals surface on the pipeline page
 * itself instead of quietly aging in a kanban column.
 */
class StaleDealsWidget extends TableWidget
{
    use OnlyVisibleOnSalesPipeline;

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
                    ->formatStateUsing(fn (int $state) => "{$state} روز")
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
}
