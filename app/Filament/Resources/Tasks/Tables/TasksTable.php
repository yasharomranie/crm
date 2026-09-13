<?php

namespace App\Filament\Resources\Tasks\Tables;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Filament\Resources\FormSubmissions\FormSubmissionResource;
use App\Models\Task;
use App\Models\TaskChecklistTemplate;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TasksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('source'))
            ->columns([
                TextColumn::make('title')->label('عنوان')->searchable(),
                TextColumn::make('department.name')->label('دپارتمان')->placeholder('—'),
                TextColumn::make('assignee.name')->label('مسئول')->placeholder('—'),
                TextColumn::make('priority')->label('اولویت')->badge(),
                TextColumn::make('status')->label('وضعیت')->badge(),
                TextColumn::make('due_at')->label('مهلت')->dateTime('Y/m/d H:i')->placeholder('—'),
                TextColumn::make('tags.name')->label('برچسب‌ها')->badge()->separator(','),
            ])
            ->defaultSort('due_at')
            ->filters([
                SelectFilter::make('status')->label('وضعیت')->options(TaskStatus::class),
                SelectFilter::make('priority')->label('اولویت')->options(TaskPriority::class),
            ])
            ->recordActions([
                Action::make('completeWithForm')
                    ->label('تکمیل با فرم')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->color('primary')
                    ->visible(fn (Task $record) => filled(static::linkedFormTemplateId($record)))
                    ->url(fn (Task $record) => FormSubmissionResource::getUrl('create', [
                        'form_template_id' => static::linkedFormTemplateId($record),
                    ]))
                    ->openUrlInNewTab(),
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
     * The form template linked to this task's originating recurring
     * checklist template, if it was generated from one and a form was
     * configured on it - null for every other task.
     */
    private static function linkedFormTemplateId(Task $task): ?int
    {
        if (! $task->source instanceof TaskChecklistTemplate) {
            return null;
        }

        return $task->source->form_template_id;
    }
}
