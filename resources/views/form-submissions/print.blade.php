<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $submission->formTemplate->title }} — {{ $submission->reference_code }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            color-scheme: light;
            --ink: #1c1917;
            --ink-soft: #57534e;
            --ink-faint: #a8a29e;
            --line: #e7e5e4;
            --gold: #9c6b1f;
            --paper: #f4f4f3;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 2rem 1.25rem;
            background: var(--paper);
            font-family: 'Vazirmatn', Tahoma, 'B Nazanin', Arial, sans-serif;
            color: var(--ink);
        }

        .toolbar {
            max-width: 50rem;
            margin: 0 auto 1rem;
            display: flex;
            justify-content: flex-end;
            gap: .5rem;
        }

        .toolbar button, .toolbar a {
            font-family: inherit;
            font-size: .875rem;
            font-weight: 500;
            border-radius: .5rem;
            padding: .55rem 1.1rem;
            border: 1px solid #d6d3d1;
            background: #fff;
            color: var(--ink);
            cursor: pointer;
            text-decoration: none;
        }

        .toolbar button.primary {
            background: var(--gold);
            color: #fff;
            border-color: var(--gold);
        }

        .sheet {
            max-width: 50rem;
            margin: 0 auto;
            background: #fff;
            border-radius: .75rem;
            box-shadow: 0 1px 3px rgb(0 0 0 / .1);
            padding: 3rem 3.5rem;
        }

        /* ---------- Letterhead ---------- */

        .letterhead {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            padding-bottom: 1.25rem;
            border-bottom: 2px solid var(--ink);
            margin-bottom: 1.5rem;
        }

        .letterhead .company-name {
            font-size: 1.25rem;
            font-weight: 800;
            margin: 0 0 .3rem;
        }

        .letterhead .company-meta {
            font-size: .78rem;
            color: var(--ink-soft);
        }

        .letterhead .doc-tag {
            flex-shrink: 0;
            text-align: left;
            font-size: .72rem;
            color: var(--ink-faint);
            line-height: 1.8;
            white-space: nowrap;
        }

        h1.form-title {
            font-size: 1.15rem;
            font-weight: 800;
            margin: 0 0 .35rem;
        }

        .sub-meta {
            font-size: .8rem;
            color: var(--ink-soft);
            margin-bottom: 1rem;
        }

        #qrcode {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 1rem;
        }

        .status {
            display: inline-block;
            font-size: .72rem;
            font-weight: 600;
            padding: .2rem .7rem;
            border-radius: 999px;
            margin-bottom: 1.5rem;
        }

        /* ---------- Fields ---------- */

        dl.fields {
            margin: 0 0 1.75rem;
        }

        dl.fields > div {
            padding-block: .6rem;
            border-bottom: 1px solid var(--line);
        }

        dl.fields dt {
            font-size: .76rem;
            color: var(--ink-soft);
        }

        dl.fields dd {
            margin: .2rem 0 0;
            font-size: .92rem;
        }

        dl.fields img.signature {
            height: 64px;
            border: 1px solid var(--line);
            border-radius: .35rem;
        }

        table.value-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .8rem;
        }

        table.value-table th, table.value-table td {
            border: 1px solid var(--line);
            padding: .4rem .5rem;
            text-align: start;
        }

        table.value-table th {
            background: var(--paper);
            font-weight: 600;
            color: var(--ink-soft);
        }

        /* ---------- Merged document (from a form template's متن سند) ---------- */

        .document-body {
            margin: 0 0 1.75rem;
            font-size: .95rem;
            line-height: 2.1;
        }

        .document-body p {
            margin: 0 0 .75rem;
        }

        .document-body h1, .document-body h2, .document-body h3 {
            font-size: 1rem;
            font-weight: 700;
            margin: 1rem 0 .5rem;
        }

        .document-body ul, .document-body ol {
            margin: 0 0 .75rem;
            padding-inline-start: 1.5rem;
        }

        .document-body img {
            max-width: 100%;
        }

        h2.section {
            font-size: .95rem;
            font-weight: 700;
            margin: 0 0 .75rem;
        }

        ol.steps {
            list-style: none;
            padding: 0;
            margin: 0 0 1rem;
            display: flex;
            flex-wrap: wrap;
            gap: .4rem;
        }

        ol.steps li {
            font-size: .72rem;
            padding: .25rem .65rem;
            border-radius: 999px;
            background: #f0f0f1;
            color: #52525b;
        }

        ol.steps li.done {
            background: #dcfce7;
            color: #166534;
        }

        ol.steps li.current {
            background: #fef3c7;
            color: #92400e;
        }

        ul.actions {
            list-style: none;
            padding: 0;
            margin: 0 0 2rem;
        }

        ul.actions li {
            font-size: .85rem;
            padding-block: .4rem;
            border-bottom: 1px solid var(--line);
        }

        ul.actions li:last-child {
            border-bottom: none;
        }

        ul.actions .comment {
            font-size: .78rem;
            color: #52525b;
            margin-top: .15rem;
        }

        ul.actions .when {
            font-size: .72rem;
            color: var(--ink-faint);
        }

        footer.doc-footer {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--line);
            font-size: .7rem;
            color: var(--ink-faint);
            text-align: center;
        }

        @media (max-width: 640px) {
            .sheet {
                padding: 1.75rem 1.25rem;
            }

            .letterhead {
                flex-direction: column;
            }

            .letterhead .doc-tag {
                text-align: right;
            }
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }

            .toolbar {
                display: none;
            }

            .sheet {
                box-shadow: none;
                border-radius: 0;
                padding: 0;
                max-width: none;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('form-submissions.export-word', $submission) }}">دانلود Word</a>
        <button type="button" class="primary" onclick="window.print()">چاپ / دانلود PDF</button>
    </div>

    <div class="sheet">
        <div class="letterhead">
            @if ($company = $submission->company())
                <div>
                    <p class="company-name">{{ $company->name }}</p>
                    @if ($company->national_id || $company->address)
                        <p class="company-meta">
                            @if ($company->national_id)
                                شناسه ملی: {{ $company->national_id }}
                            @endif
                            @if ($company->address)
                                <br>{{ $company->address }}
                            @endif
                        </p>
                    @endif
                </div>
            @else
                <div></div>
            @endif
            <div class="doc-tag">
                شمارهٔ پیگیری: {{ $submission->reference_code }}<br>
                {{ $submission->submitted_at?->toJalali('Y/m/d H:i') }}
            </div>
        </div>

        <div id="qrcode"></div>

        <h1 class="form-title">{{ $submission->formTemplate->title }}</h1>
        <p class="sub-meta">
            {{ $submission->department?->name ?? 'بدون دپارتمان' }} ·
            ثبت‌کننده: {{ $submission->submitter?->name ?? '—' }}
        </p>

        @php
            $statusColors = ['draft' => '#e4e4e7;color:#3f3f46', 'pending_approval' => '#fef3c7;color:#92400e', 'approved' => '#dcfce7;color:#166534', 'rejected' => '#fee2e2;color:#991b1b', 'cancelled' => '#e4e4e7;color:#52525b'];
        @endphp
        <span class="status" style="background:{{ $statusColors[$submission->status->value] ?? '#e4e4e7' }}">
            {{ $submission->status->getLabel() }}
        </span>

        @php $mergedDocument = $submission->mergedDocumentBody(); @endphp
        @if ($mergedDocument)
            {{-- The form template has a "متن سند" - fixed boilerplate text
                with the real answers merged into it - so render that
                instead of the generic label/value list below. --}}
            <div class="document-body">{!! $mergedDocument !!}</div>
        @else
            <dl class="fields">
                {{-- ->withTrashed(): show fields even if removed from the
                    form builder since this was submitted - they're soft-
                    deleted, not gone, precisely so this historical record
                    stays accurate. --}}
                @foreach ($submission->formTemplate->fields()->withTrashed()->get() as $field)
                    @php $value = $submission->values->firstWhere('form_field_id', $field->id); @endphp
                    <div>
                        <dt>{{ $field->label }}</dt>
                        <dd>
                            @if (! $value)
                                —
                            @elseif ($field->field_type->value === 'checkbox')
                                {{ $value->value ? 'بله' : 'خیر' }}
                            @elseif ($field->field_type->value === 'date_jalali')
                                {{ optional($value->value)->toJalali('Y/m/d') ?? '—' }}
                            @elseif ($field->field_type->value === 'signature')
                                <img class="signature" src="{{ $value->value }}" alt="امضا">
                            @elseif ($field->field_type->value === 'gps' && is_array($value->value))
                                @if (isset($value->value['lat'], $value->value['lng']))
                                    <x-gps-map :lat="$value->value['lat']" :lng="$value->value['lng']" />
                                @else
                                    —
                                @endif
                            @elseif ($field->field_type->value === 'file')
                                پیوست ثبت شده
                            @elseif ($field->field_type->value === 'table' && is_array($value->value))
                                <table class="value-table">
                                    <thead>
                                        <tr>
                                            @foreach ($field->table_columns ?? [] as $column)
                                                <th>{{ $column['label'] }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($value->value as $row)
                                            <tr>
                                                @foreach ($field->table_columns ?? [] as $column)
                                                    <td>{{ $row[$column['key']] ?? '—' }}</td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                {{ $value->value }}
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        @endif

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
                            <span class="when">({{ $action->acted_at->toJalali('Y/m/d H:i') }})</span>
                            @if ($action->comment)
                                <div class="comment">{{ $action->comment }}</div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif

        <footer class="doc-footer">
            این سند توسط سامانهٔ گنج‌نامه تولید شده است — {{ now()->toJalali('Y/m/d H:i') }}
        </footer>
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
