<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $submission->formTemplate->title }} — {{ $submission->reference_code }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 2rem 1.25rem;
            background: #f4f4f5;
            font-family: 'Vazirmatn', Tahoma, 'B Nazanin', Arial, sans-serif;
            color: #18181b;
        }

        .toolbar { max-width: 48rem; margin: 0 auto 1rem; display: flex; justify-content: flex-end; }

        .toolbar button {
            font-family: inherit;
            font-size: .875rem;
            border-radius: .5rem;
            padding: .5rem 1rem;
            border: 1px solid #18181b;
            background: #18181b;
            color: #fff;
            cursor: pointer;
        }

        .sheet {
            max-width: 48rem;
            margin: 0 auto;
            background: #fff;
            border-radius: .75rem;
            box-shadow: 0 1px 3px rgb(0 0 0 / .1);
            padding: 2.5rem 3rem;
        }

        .head { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; margin-bottom: 1.5rem; border-bottom: 1px solid #e4e4e7; padding-bottom: 1.25rem; }
        .head h1 { font-size: 1.15rem; margin: 0 0 .25rem; }
        .head .ref { font-family: monospace; font-size: .8rem; color: #71717a; }
        .head .meta { font-size: .78rem; color: #71717a; margin-top: .3rem; }
        #qrcode { flex-shrink: 0; }

        .status {
            display: inline-block;
            font-size: .72rem;
            font-weight: 600;
            padding: .2rem .7rem;
            border-radius: 999px;
            margin-bottom: 1.5rem;
        }

        dl.fields { margin: 0 0 1.75rem; }
        dl.fields > div { padding-block: .55rem; border-bottom: 1px solid #f0f0f1; }
        dl.fields dt { font-size: .74rem; color: #71717a; }
        dl.fields dd { margin: .15rem 0 0; font-size: .92rem; }
        dl.fields img.signature { height: 64px; border: 1px solid #e4e4e7; border-radius: .35rem; }

        h2.section { font-size: .95rem; margin: 0 0 .75rem; }

        ol.steps { list-style: none; padding: 0; margin: 0 0 1rem; display: flex; flex-wrap: wrap; gap: .4rem; }
        ol.steps li { font-size: .72rem; padding: .25rem .65rem; border-radius: 999px; background: #f0f0f1; color: #52525b; }
        ol.steps li.done { background: #dcfce7; color: #166534; }
        ol.steps li.current { background: #fef3c7; color: #92400e; }

        ul.actions { list-style: none; padding: 0; margin: 0; }
        ul.actions li { font-size: .85rem; padding-block: .4rem; border-bottom: 1px solid #f0f0f1; }
        ul.actions li:last-child { border-bottom: none; }
        ul.actions .comment { font-size: .78rem; color: #52525b; margin-top: .15rem; }
        ul.actions .when { font-size: .72rem; color: #a1a1aa; }

        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none; }
            .sheet { box-shadow: none; border-radius: 0; padding: 0; max-width: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">چاپ / ذخیرهٔ PDF</button>
    </div>

    <div class="sheet">
        <div class="head">
            <div>
                <h1>{{ $submission->formTemplate->title }}</h1>
                <div class="ref">{{ $submission->reference_code }}</div>
                <div class="meta">
                    {{ $submission->department?->name ?? 'بدون دپارتمان' }} ·
                    ثبت‌کننده: {{ $submission->submitter?->name ?? '—' }} ·
                    {{ $submission->submitted_at?->format('Y/m/d H:i') }}
                </div>
            </div>
            <div id="qrcode"></div>
        </div>

        @php
            $statusColors = ['draft' => '#e4e4e7;color:#3f3f46', 'pending_approval' => '#fef3c7;color:#92400e', 'approved' => '#dcfce7;color:#166534', 'rejected' => '#fee2e2;color:#991b1b', 'cancelled' => '#e4e4e7;color:#52525b'];
        @endphp
        <span class="status" style="background:{{ $statusColors[$submission->status->value] ?? '#e4e4e7' }}">
            {{ $submission->status->getLabel() }}
        </span>

        <dl class="fields">
            @foreach ($submission->formTemplate->fields as $field)
                @php $value = $submission->values->firstWhere('form_field_id', $field->id); @endphp
                <div>
                    <dt>{{ $field->label }}</dt>
                    <dd>
                        @if (! $value)
                            —
                        @elseif ($field->field_type->value === 'checkbox')
                            {{ $value->value ? 'بله' : 'خیر' }}
                        @elseif ($field->field_type->value === 'signature')
                            <img class="signature" src="{{ $value->value }}" alt="امضا">
                        @elseif ($field->field_type->value === 'gps' && is_array($value->value))
                            {{ $value->value['lat'] ?? '—' }}, {{ $value->value['lng'] ?? '—' }}
                        @elseif ($field->field_type->value === 'file')
                            پیوست ثبت شده
                        @else
                            {{ $value->value }}
                        @endif
                    </dd>
                </div>
            @endforeach
        </dl>

        @if ($instance = $submission->workflowInstance)
            <h2 class="section">روند تأیید</h2>
            <ol class="steps">
                @foreach ($instance->workflowDefinition->steps as $step)
                    @php
                        $isCurrent = $instance->current_step_id === $step->id && $instance->status->value === 'in_progress';
                        $isPast = $step->step_order < ($instance->currentStep?->step_order ?? PHP_INT_MAX);
                    @endphp
                    <li class="{{ $isCurrent ? 'current' : ($isPast ? 'done' : '') }}">{{ $step->name }}</li>
                @endforeach
            </ol>

            @if ($instance->actions->isNotEmpty())
                <ul class="actions">
                    @foreach ($instance->actions as $action)
                        <li>
                            <strong>{{ $action->action->getLabel() }}</strong> — {{ $action->actor->name }}
                            <span class="when">({{ $action->acted_at->format('Y/m/d H:i') }})</span>
                            @if ($action->comment)
                                <div class="comment">{{ $action->comment }}</div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif
    </div>

    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script>
        if (window.QRCode) {
            new QRCode(document.getElementById('qrcode'), {
                text: @json(route('form-submissions.print', $submission)),
                width: 84,
                height: 84,
            });
        }
    </script>
</body>
</html>
