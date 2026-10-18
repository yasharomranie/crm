<?php

namespace App\Filament\Actions;

use App\Enums\CrmActivityType;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

/**
 * A manual "log a call/note/meeting" action reusable on any CRM subject
 * (Guest, Deal, ...) - writes to the same spatie/laravel-activitylog
 * `activity_log` table the subject's own LogsActivity trait auto-logs field
 * changes into, so both show up together in one chronological timeline.
 */
class LogCrmActivityAction
{
    public static function make(): Action
    {
        return Action::make('logCrmActivity')
            ->label('ثبت تعامل جدید')
            ->icon('heroicon-o-plus-circle')
            ->schema([
                Select::make('type')
                    ->label('نوع تعامل')
                    ->options(CrmActivityType::class)
                    ->required(),
                Textarea::make('description')
                    ->label('توضیحات')
                    ->required()
                    ->rows(3),
            ])
            ->action(function (array $data, Model $record): void {
                $type = $data['type'] instanceof CrmActivityType ? $data['type'] : CrmActivityType::from($data['type']);

                activity('crm_interactions')
                    ->performedOn($record)
                    ->causedBy(auth()->user())
                    ->withProperties(['type' => $type->value])
                    ->event($type->value)
                    ->log($data['description']);

                Notification::make()
                    ->title('تعامل با موفقیت ثبت شد.')
                    ->success()
                    ->send();
            });
    }
}
