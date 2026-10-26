<?php

namespace App\Filament\Resources\DigitalProjects\Tables;

use App\Enums\DigitalProjectActionStatus;
use App\Enums\DigitalProjectChannel;
use App\Enums\DigitalProjectStatus;
use App\Filament\Resources\DigitalProjects\DigitalProjectResource;
use App\Filament\Support\TableExports;
use App\Models\DigitalProject;
use Closure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;
use Morilog\Jalali\Jalalian;

class DigitalProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['latestLog', 'seoProject.siteCheck'])->withCount([
                'actions as open_actions_count' => fn (Builder $actions) => $actions->whereIn('status', DigitalProjectActionStatus::unfinishedValues()),
                'actions as overdue_actions_count' => fn (Builder $actions) => $actions
                    ->whereIn('status', DigitalProjectActionStatus::unfinishedValues())
                    ->whereDate('due_on', '<', today()),
            ]))
            ->columns([
                TextColumn::make('name')
                    ->label('پروژه')
                    ->searchable()
                    ->description(fn (DigitalProject $record) => $record->handle)
                    ->wrap(),
                TextColumn::make('status')->label('وضعیت')->badge(),
                TextColumn::make('site_health')
                    ->label('وضعیت سایت')
                    ->state(fn (DigitalProject $record) => $record->siteHealth()['label'] ?? null)
                    ->badge()
                    ->color(fn (DigitalProject $record) => $record->siteHealth()['color'] ?? 'gray')
                    ->placeholder('—'),
                TextColumn::make('open_actions_count')
                    ->label('اقدام باز')
                    ->formatStateUsing(fn ($state) => Number::toPersian((int) $state))
                    ->badge()
                    ->color(fn (DigitalProject $record) => $record->overdue_actions_count > 0 ? 'danger' : ($record->open_actions_count > 0 ? 'warning' : 'gray'))
                    ->tooltip(fn (DigitalProject $record) => $record->overdue_actions_count > 0 ? Number::toPersian((int) $record->overdue_actions_count).' اقدام از مهلت گذشته' : null),
                TextColumn::make('latestLog.body')
                    ->label('آخرین گزارش')
                    ->limit(60)
                    ->placeholder('گزارشی ثبت نشده')
                    ->description(fn (DigitalProject $record) => $record->latestLog ? Jalalian::fromCarbon($record->latestLog->logged_on)->format('Y/m/d') : null)
                    ->wrap(),
                TextColumn::make('owner.name')->label('مسئول')->placeholder('—'),
                TextColumn::make('next_review_at')
                    ->label('بررسی بعدی')
                    ->jalaliDate('Y/m/d')
                    ->placeholder('—')
                    ->color(fn (DigitalProject $record) => $record->isReviewOverdue() ? 'danger' : null)
                    ->tooltip(fn (DigitalProject $record) => $record->isReviewOverdue() ? 'موعد بررسی گذشته است' : null),
                TextColumn::make('updated_at')->label('آخرین تغییر')->jalaliDate('Y/m/d')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                TableExports::excel('پروژه‌های-دیجیتال', self::exportColumns()),
                TableExports::pdf('فهرست پروژه‌های دیجیتال', self::exportColumns()),
            ])
            ->defaultGroup(
                Group::make('channel')
                    ->label('نوع پروژه')
                    ->getTitleFromRecordUsing(fn (DigitalProject $record) => $record->channel->groupTitle().' ('.Number::toPersian(DigitalProject::query()->where('channel', $record->channel->value)->count()).')')
                    ->orderQueryUsing(fn (Builder $query, string $direction) => $query->orderByRaw(DigitalProjectChannel::orderSql().' '.$direction))
                    ->collapsible(),
            )
            ->groupingSettingsHidden()
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('channel')->label('نوع پروژه')->options(DigitalProjectChannel::class),
                SelectFilter::make('status')->label('وضعیت')->options(DigitalProjectStatus::class),
                Filter::make('without_recent_log')
                    ->label('بدون گزارش بیش از ۷ روز')
                    ->query(fn (Builder $query) => $query->withoutRecentLog())
                    ->toggle(),
                Filter::make('site_problem')
                    ->label('سایت دارای مشکل')
                    ->query(fn (Builder $query) => $query->withSiteProblem())
                    ->toggle(),
                Filter::make('review_overdue')
                    ->label('بررسی عقب‌افتاده')
                    ->query(fn (Builder $query) => $query->reviewOverdue())
                    ->toggle(),
            ])
            ->recordUrl(fn (DigitalProject $record) => DigitalProjectResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make(),
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
     * @return array<string, Closure(DigitalProject): mixed>
     */
    public static function exportColumns(): array
    {
        return [
            'نوع' => fn (DigitalProject $project) => $project->channel->getLabel(),
            'پروژه' => fn (DigitalProject $project) => $project->name,
            'آدرس یا آیدی' => fn (DigitalProject $project) => $project->handle,
            'وضعیت' => fn (DigitalProject $project) => $project->status->getLabel(),
            'مسئول' => fn (DigitalProject $project) => $project->owner?->name,
            'اقدام باز' => fn (DigitalProject $project) => Number::toPersian($project->actions()->whereIn('status', DigitalProjectActionStatus::unfinishedValues())->count()),
            'آخرین گزارش' => fn (DigitalProject $project) => $project->latestLog ? Jalalian::fromCarbon($project->latestLog->logged_on)->format('Y/m/d').' — '.$project->latestLog->body : null,
            'بررسی بعدی' => fn (DigitalProject $project) => $project->next_review_at ? Jalalian::fromCarbon($project->next_review_at)->format('Y/m/d') : null,
        ];
    }
}
