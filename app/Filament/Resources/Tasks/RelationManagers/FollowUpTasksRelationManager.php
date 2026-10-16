<?php

namespace App\Filament\Resources\Tasks\RelationManagers;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Task;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * "Call them back Thursday" - scheduled follow-ups on a guest or a deal
 * (see HasFollowUpTasks). Shared by both resources: the relationship name
 * is the same on each, and the underlying rows are plain Task records, so
 * they also show up in the normal Tasks resource and on the dashboard's
 * "پیگیری‌های امروز من" widget.
 */
class FollowUpTasksRelationManager extends RelationManager
{
    protected static string $relationship = 'followUpTasks';

    protected static ?string $title = 'پیگیری‌ها و یادآورها';

    protected static ?string $modelLabel = 'پیگیری';

    protected static ?string $pluralModelLabel = 'پیگیری‌ها';

    /**
     * Filament makes relation managers read-only on a resource's View page
     * by default - but scheduling a follow-up while looking at a guest's
     * profile is the whole point of this one.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('چه کاری باید انجام شود؟')
                    ->helperText('مثلاً «تماس برای اعلام قیمت نهایی»')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->label('توضیحات (اختیاری)')
                    ->rows(2)
                    ->columnSpanFull(),
                DateTimePicker::make('due_at')
                    ->jalali()
                    ->displayFormat('Y/m/d H:i')
                    ->label('زمان پیگیری')
                    ->helperText('در این زمان به مسئول پیگیری یادآوری ارسال می‌شود.')
                    ->required(),
                Select::make('assigned_to')
                    ->label('مسئول پیگیری')
                    ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))
                    ->default(fn () => auth()->id())
                    ->searchable()
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('due_at')
            ->columns([
                TextColumn::make('title')->label('پیگیری')->wrap(),
                TextColumn::make('assignee.name')->label('مسئول')->placeholder('—'),
                TextColumn::make('due_at')
                    ->label('زمان')
                    ->jalaliDateTime('Y/m/d H:i')
                    ->color(fn (Task $record) => $record->due_at?->isPast() && $record->status !== TaskStatus::Done ? 'danger' : null),
                TextColumn::make('status')->label('وضعیت')->badge(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('ثبت پیگیری جدید')
                    ->mutateDataUsing(fn (array $data) => [
                        ...$data,
                        'task_type' => TaskType::CrmFollowUp,
                        'assigned_by' => auth()->id(),
                    ]),
            ])
            ->recordActions([
                Action::make('markDone')
                    ->label('انجام شد')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Task $record) => ! in_array($record->status, [TaskStatus::Done, TaskStatus::Cancelled], true))
                    ->action(fn (Task $record) => $record->update([
                        'status' => TaskStatus::Done,
                        'completed_at' => now(),
                    ])),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
