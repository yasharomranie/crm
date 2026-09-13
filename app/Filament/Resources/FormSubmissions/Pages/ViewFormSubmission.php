<?php

namespace App\Filament\Resources\FormSubmissions\Pages;

use App\Enums\WorkflowActionType;
use App\Exceptions\WorkflowException;
use App\Filament\Resources\FormSubmissions\FormSubmissionResource;
use App\Filament\Resources\FormSubmissions\Schemas\FormSubmissionInfolist;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class ViewFormSubmission extends ViewRecord
{
    protected static string $resource = FormSubmissionResource::class;

    public function infolist(Schema $schema): Schema
    {
        return FormSubmissionInfolist::configure($schema);
    }

    protected function getHeaderActions(): array
    {
        $instance = $this->record->workflowInstance;
        $user = Auth::user();
        $canAct = $instance?->canBeActedOnBy($user) || $user->hasRole('admin');
        $canCancel = $instance?->status->value === 'in_progress' && $this->record->submitted_by === $user->id;

        return [
            Action::make('approve')
                ->label('تأیید')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $canAct)
                ->schema([
                    Textarea::make('comment')->label('نظر (اختیاری)'),
                ])
                ->action(fn (array $data) => $this->act(WorkflowActionType::Approve, $data['comment'] ?? null)),

            Action::make('reject')
                ->label('رد')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn () => $canAct)
                ->schema([
                    Textarea::make('comment')->label('دلیل رد')->required(),
                ])
                ->action(fn (array $data) => $this->act(WorkflowActionType::Reject, $data['comment'])),

            Action::make('refer')
                ->label('ارجاع موازی')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn () => $canAct)
                ->schema([
                    Select::make('referred_to')
                        ->label('ارجاع به')
                        ->options(fn () => User::query()->pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                    Textarea::make('comment')->label('دلیل ارجاع')->required(),
                ])
                ->action(function (array $data) {
                    $target = User::find($data['referred_to']);
                    $this->act(WorkflowActionType::ReferParallel, "ارجاع به {$target?->name}: {$data['comment']}");
                }),

            Action::make('comment')
                ->label('ثبت نظر')
                ->icon('heroicon-o-chat-bubble-left')
                ->color('gray')
                ->visible(fn () => $canAct)
                ->schema([
                    Textarea::make('comment')->label('نظر')->required(),
                ])
                ->action(fn (array $data) => $this->act(WorkflowActionType::Comment, $data['comment'])),

            Action::make('cancel')
                ->label('لغو درخواست')
                ->icon('heroicon-o-trash')
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn () => $canCancel)
                ->action(fn () => $this->act(WorkflowActionType::Cancel, null)),

            Action::make('print')
                ->label('چاپ')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn () => route('form-submissions.print', $this->record))
                ->openUrlInNewTab(),
        ];
    }

    protected function act(WorkflowActionType $type, ?string $comment): void
    {
        try {
            $this->record->workflowInstance->act(Auth::user(), $type, $comment);

            Notification::make()->title('اقدام شما با موفقیت ثبت شد.')->success()->send();
        } catch (WorkflowException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
        }
    }
}
