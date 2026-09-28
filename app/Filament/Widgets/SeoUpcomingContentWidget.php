<?php

namespace App\Filament\Widgets;

use App\Enums\SeoContentItemStatus;
use App\Models\SeoContentItem;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Number;

/**
 * Unpublished content items nearest their deadline - "داشبورد سئو"'s own
 * "needs attention" list, the same role StaleDealsWidget plays for the
 * sales pipeline, so a piece of content quietly overdue in the calendar
 * surfaces here instead of only being visible by digging into تقویم محتوا.
 */
class SeoUpcomingContentWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('محتوای نزدیک به مهلت')
            ->description('آیتم‌های منتشرنشده‌ای که مهلت‌شان گذشته یا تا ۷ روز آینده است')
            ->query(
                SeoContentItem::query()
                    ->where('status', '!=', SeoContentItemStatus::Published)
                    ->whereNotNull('due_date')
                    ->whereDate('due_date', '<=', now()->addDays(7)->toDateString())
                    ->with(['project', 'assignee'])
            )
            ->defaultSort('due_date')
            ->columns([
                TextColumn::make('project.name')->label('پروژه'),
                TextColumn::make('title')->label('عنوان')->wrap()->limit(60),
                TextColumn::make('status')->label('وضعیت')->badge(),
                TextColumn::make('assignee.name')->label('مسئول')->placeholder('بدون مسئول'),
                TextColumn::make('due_date')->label('مهلت')->date('Y/m/d')->sortable(),
                TextColumn::make('due_date')
                    ->label('وضعیت مهلت')
                    ->state(fn (SeoContentItem $record) => (int) now()->diffInDays($record->due_date, false))
                    ->formatStateUsing(fn (int $state) => $state < 0
                        ? Number::toPersian(abs($state)).' روز گذشته'
                        : Number::toPersian($state).' روز مانده')
                    ->badge()
                    ->color(fn (int $state) => $state < 0 ? 'danger' : 'warning'),
            ])
            ->recordActions([
                EditAction::make()->url(fn (SeoContentItem $record) => route('filament.admin.resources.seo-content-items.edit', $record)),
            ])
            ->emptyStateHeading('محتوایی نزدیک به مهلت نیست')
            ->emptyStateDescription('همه‌ی آیتم‌های محتوا یا منتشر شده‌اند یا مهلت‌شان دورتر است.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([5, 10, 25]);
    }
}
