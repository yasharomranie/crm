<?php

namespace App\Filament\Resources\FormSubmissions\Pages;

use App\Enums\WorkflowActionType;
use App\Exceptions\WorkflowException;
use App\Filament\Resources\FormSubmissions\FormSubmissionResource;
use App\Filament\Resources\FormSubmissions\Schemas\FormSubmissionInfolist;
use App\Filament\Support\DynamicFormFieldBuilder;
use App\Models\User;
use App\Models\WorkflowStep;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
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
        $canCancel = $instance?->status->value === 'in_progress' && (int) $this->record->submitted_by === (int) $user->id;

        return [
            Action::make('approve')
                ->label('تأیید')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $canAct)
                ->schema(fn () => [
                    Textarea::make('comment')->label('نظر (اختیاری)'),
                    ...$this->currentStepFieldSchema($instance?->currentStep),
                ])
                ->action(function (array $data) use ($instance) {
                    $step = $instance?->currentStep;

                    if ($this->act(WorkflowActionType::Approve, $data['comment'] ?? null)) {
                        $this->saveStepFieldValues($step, $data);
                    }
                }),

            Action::make('reject')
                ->label('رد')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn () => $canAct)
                ->schema(fn () => [
                    Textarea::make('comment')->label('دلیل رد')->required(),
                    ...$this->currentStepFieldSchema($instance?->currentStep),
                ])
                ->action(function (array $data) use ($instance) {
                    $step = $instance?->currentStep;

                    if ($this->act(WorkflowActionType::Reject, $data['comment'])) {
                        $this->saveStepFieldValues($step, $data);
                    }
                }),

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

    protected function act(WorkflowActionType $type, ?string $comment): bool
    {
        try {
            $this->record->workflowInstance->act(Auth::user(), $type, $comment);

            Notification::make()->title('اقدام شما با موفقیت ثبت شد.')->success()->send();

            return true;
        } catch (WorkflowException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return false;
        }
    }

    /**
     * Builds the extra form fields (if any) that belong specifically to
     * $step - e.g. "نظر دفتر فنی" only appearing while acting at the
     * "تأیید دفتر فنی" step - so whoever approves/rejects here fills them
     * in as part of that same action, instead of the original submitter.
     *
     * @return array<int, Field>
     */
    protected function currentStepFieldSchema(?WorkflowStep $step): array
    {
        return $step?->fields->map(fn ($field) => DynamicFormFieldBuilder::make($field))->all() ?? [];
    }

    /**
     * Saves whichever of $step's own fields were included in $data - the
     * mirror image of CreateFormSubmission::handleRecordCreation()'s loop,
     * but for a step's fields being filled in later during the approval
     * cycle instead of at initial submission. updateOrCreate() (not
     * create()) since the same step could plausibly be acted on more than
     * once (e.g. after a referral back).
     */
    protected function saveStepFieldValues(?WorkflowStep $step, array $data): void
    {
        foreach ($step?->fields ?? [] as $field) {
            if (! array_key_exists($field->field_key, $data)) {
                continue;
            }

            $value = $data[$field->field_key];

            // blank() (not a plain === null/'' check): a Table field with
            // no rows added submits as [], which needs skipping here
            // exactly like an untouched text field would.
            if (blank($value)) {
                continue;
            }

            $this->record->values()->updateOrCreate(
                ['form_field_id' => $field->id],
                DynamicFormFieldBuilder::valueColumns($field, $value),
            );
        }
    }
}
