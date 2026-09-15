@php
    use App\Enums\FormFieldType;
    use App\Enums\WorkflowActionType;

    $submission = $record;
    $template = $submission->formTemplate;
    $instance = $submission->workflowInstance;
    $valuesByField = $submission->values->keyBy('form_field_id');

    $actionColors = [
        WorkflowActionType::Approve->value => 'text-success-600 dark:text-success-400',
        WorkflowActionType::Reject->value => 'text-danger-600 dark:text-danger-400',
        WorkflowActionType::ReferParallel->value => 'text-warning-600 dark:text-warning-400',
        WorkflowActionType::Cancel->value => 'text-gray-500',
        WorkflowActionType::Comment->value => 'text-gray-600 dark:text-gray-400',
    ];
@endphp

<div class="space-y-6">
    {{-- سربرگ --}}
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <div class="text-xs text-gray-500 dark:text-gray-400">شمارهٔ پیگیری</div>
                <div class="font-mono text-sm font-semibold text-gray-950 dark:text-white">{{ $submission->reference_code }}</div>
            </div>
            <x-filament::badge :color="$submission->status->getColor()">{{ $submission->status->getLabel() }}</x-filament::badge>
        </div>

        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">نوع فرم</dt>
                <dd class="font-medium text-gray-950 dark:text-white">{{ $template->title }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">دپارتمان</dt>
                <dd class="font-medium text-gray-950 dark:text-white">{{ $submission->department?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">ثبت‌کننده</dt>
                <dd class="font-medium text-gray-950 dark:text-white">{{ $submission->submitter?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">تاریخ ثبت</dt>
                <dd class="font-medium text-gray-950 dark:text-white">{{ $submission->submitted_at?->toJalali('Y/m/d H:i') }}</dd>
            </div>
        </dl>
    </div>

    {{-- مقادیر فرم --}}
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
        <h3 class="mb-3 text-sm font-semibold text-gray-950 dark:text-white">اطلاعات ثبت‌شده</h3>
        @php $mergedDocument = $submission->mergedDocumentBody(); @endphp
        @if ($mergedDocument)
            {{-- The form template has a "متن سند" - fixed boilerplate text
                with the real answers merged into it - so show that instead
                of the generic label/value list below. --}}
            <div class="text-sm leading-7 text-gray-900 [&_img]:max-w-full [&_p]:mb-3 dark:text-gray-100">{!! $mergedDocument !!}</div>
        @else
            <dl class="space-y-3">
                {{-- ->withTrashed(): a field removed from the form builder
                    since this submission was made must still show here -
                    it's soft-deleted, not gone, precisely so old answers
                    stay visible. --}}
                @foreach ($template->fields()->withTrashed()->get() as $field)
                    @php $value = $valuesByField->get($field->id); @endphp
                    <div class="border-b border-gray-100 pb-3 last:border-0 last:pb-0 dark:border-white/5">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $field->label }}</dt>
                        <dd class="mt-0.5 text-sm text-gray-900 dark:text-gray-100">
                            @if (! $value)
                                <span class="text-gray-400">—</span>
                            @elseif ($field->field_type === FormFieldType::Checkbox)
                                {{ $value->value ? 'بله' : 'خیر' }}
                            @elseif ($field->field_type === FormFieldType::DateJalali)
                                {{ optional($value->value)->toJalali('Y/m/d') ?? '—' }}
                            @elseif ($field->field_type === FormFieldType::Signature)
                                <img src="{{ $value->value }}" alt="امضا" class="h-20 rounded border border-gray-200 bg-white dark:border-white/10" />
                            @elseif ($field->field_type === FormFieldType::Gps && is_array($value->value))
                                @if (isset($value->value['lat'], $value->value['lng']))
                                    <x-gps-map :lat="$value->value['lat']" :lng="$value->value['lng']" class="max-w-md" />
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            @elseif ($field->field_type === FormFieldType::File)
                                <a href="{{ route('form-submissions.files.download', ['submission' => $submission, 'value' => $value]) }}" class="text-primary-600 underline dark:text-primary-400">دانلود فایل پیوست</a>
                            @elseif ($field->field_type === FormFieldType::Table && is_array($value->value))
                                <div class="overflow-x-auto">
                                    <table class="min-w-full border-collapse text-xs">
                                        <thead>
                                            <tr>
                                                @foreach ($field->options['columns'] ?? [] as $column)
                                                    <th class="border border-gray-200 bg-gray-50 px-2 py-1 text-start font-medium text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">{{ $column['label'] }}</th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($value->value as $row)
                                                <tr>
                                                    @foreach ($field->options['columns'] ?? [] as $column)
                                                        <td class="border border-gray-200 px-2 py-1 text-gray-900 dark:border-white/10 dark:text-gray-100">{{ $row[$column['key']] ?? '—' }}</td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                {{ $value->value }}
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        @endif
    </div>

    {{-- گردش‌کار --}}
    @if ($instance)
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <h3 class="mb-3 text-sm font-semibold text-gray-950 dark:text-white">روند تأیید</h3>

            <ol class="mb-4 flex flex-wrap gap-2">
                @foreach ($instance->workflowDefinition->steps as $step)
                    @php
                        $isCurrent = $instance->current_step_id === $step->id && $instance->status->value === 'in_progress';
                        $isPast = $step->step_order < ($instance->currentStep?->step_order ?? PHP_INT_MAX);
                    @endphp
                    <li @class([
                        'rounded-full px-3 py-1 text-xs font-medium',
                        'bg-warning-100 text-warning-800 dark:bg-warning-500/10 dark:text-warning-300' => $isCurrent,
                        'bg-success-100 text-success-800 dark:bg-success-500/10 dark:text-success-300' => $isPast && ! $isCurrent,
                        'bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400' => ! $isCurrent && ! $isPast,
                    ])>
                        {{ $step->name }}
                    </li>
                @endforeach
            </ol>

            @if ($instance->actions->isNotEmpty())
                <ul class="space-y-2 border-t border-gray-100 pt-3 dark:border-white/5">
                    @foreach ($instance->actions as $action)
                        <li class="text-sm">
                            <span class="font-medium {{ $actionColors[$action->action->value] ?? '' }}">{{ $action->action->getLabel() }}</span>
                            توسط <span class="font-medium text-gray-900 dark:text-gray-100">{{ $action->actor->name }}</span>
                            <span class="text-xs text-gray-400">— {{ $action->acted_at->toJalali('Y/m/d H:i') }}</span>
                            @if ($action->comment)
                                <div class="mt-0.5 text-xs text-gray-600 dark:text-gray-400">{{ $action->comment }}</div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</div>
