<?php

namespace App\Filament\Widgets;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Filament\Resources\Guests\GuestResource;
use App\Models\Deal;
use App\Models\Guest;
use App\Models\Task;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * The signed-in user's own CRM follow-ups that are due today or already
 * overdue - the "who do I need to call first thing" list, driven straight
 * from the database so it's accurate the moment the dashboard opens,
 * independent of whether the scheduled reminder push ever fired.
 */
class MyFollowUpsWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('پیگیری‌های امروز من')
            ->description('پیگیری‌های مشتری و معامله که زمانشان رسیده یا گذشته است')
            ->query(
                Task::query()
                    ->where('task_type', TaskType::CrmFollowUp)
                    ->where('assigned_to', auth()->id())
                    ->whereIn('status', [TaskStatus::Todo, TaskStatus::InProgress, TaskStatus::Overdue])
                    ->where('due_at', '<=', now()->endOfDay())
                    ->with('source')
            )
            ->defaultSort('due_at')
            ->columns([
                TextColumn::make('title')->label('پیگیری')->wrap(),
                TextColumn::make('source')
                    ->label('مربوط به')
                    ->state(fn (Task $record) => static::subjectLabel($record))
                    ->placeholder('—'),
                TextColumn::make('due_at')
                    ->label('زمان')
                    ->jalaliDateTime('Y/m/d H:i')
                    ->color(fn (Task $record) => $record->due_at->isPast() ? 'danger' : null),
            ])
            ->recordActions([
                Action::make('markDone')
                    ->label('انجام شد')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(fn (Task $record) => $record->update([
                        'status' => TaskStatus::Done,
                        'completed_at' => now(),
                    ])),
                Action::make('open')
                    ->label('مشاهده')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (Task $record) => static::subjectUrl($record))
                    ->visible(fn (Task $record) => static::subjectUrl($record) !== null),
            ])
            ->emptyStateHeading('پیگیری معوقه‌ای ندارید')
            ->emptyStateDescription('وقتی برای یک مشتری یا معامله پیگیری ثبت کنید، در زمان مقرر اینجا می‌آید.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([5, 10, 25]);
    }

    private static function subjectLabel(Task $task): ?string
    {
        return match (true) {
            $task->source instanceof Guest => 'مشتری: '.$task->source->name,
            $task->source instanceof Deal => 'معامله: '.$task->source->title,
            default => null,
        };
    }

    private static function subjectUrl(Task $task): ?string
    {
        return match (true) {
            $task->source instanceof Guest => GuestResource::getUrl('view', ['record' => $task->source]),
            $task->source instanceof Deal => route('filament.admin.resources.deals.edit', $task->source),
            default => null,
        };
    }
}
