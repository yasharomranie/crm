<!doctype html>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="utf-8">
<title>{{ $submission->formTemplate->title }}</title>
{{--
    Same dependency-free "Word export" technique as contracts.word.blade.php:
    Word opens HTML served as a .doc file natively. No QR code here (it's
    drawn by client-side JS, which Word doesn't run) - the reference code
    printed in the letterhead is the same lookup key.
--}}
<!--[if gte mso 9]>
<xml>
<w:WordDocument>
<w:View>Print</w:View>
<w:Zoom>100</w:Zoom>
<w:DoNotOptimizeForBrowser/>
</w:WordDocument>
</xml>
<![endif]-->
<style>
    @page Section1 {
        size: 21cm 29.7cm;
        margin: 2.5cm 2cm 2.5cm 2cm;
        mso-header-margin: 1cm;
        mso-footer-margin: 1cm;
    }

    div.Section1 {
        page: Section1;
    }

    body {
        font-family: 'B Nazanin', Tahoma, Arial, sans-serif;
        direction: rtl;
        color: #1c1917;
        font-size: 12pt;
        line-height: 1.8;
    }

    .letterhead {
        border-bottom: 2pt solid #1c1917;
        padding-bottom: 10pt;
        margin-bottom: 14pt;
    }

    .letterhead .company-name {
        font-size: 15pt;
        font-weight: bold;
        margin: 0 0 4pt;
    }

    .letterhead .company-meta {
        font-size: 9pt;
        color: #57534e;
    }

    .letterhead .doc-tag {
        font-size: 9pt;
        color: #78716c;
        margin-top: 6pt;
    }

    h1.form-title {
        font-size: 14pt;
        font-weight: bold;
        margin: 0 0 4pt;
    }

    p.sub-meta {
        font-size: 10pt;
        color: #44403c;
        margin-bottom: 6pt;
    }

    .status {
        display: inline-block;
        font-size: 9.5pt;
        font-weight: bold;
        padding: 2pt 8pt;
        margin-bottom: 14pt;
    }

    table.fields {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 18pt;
    }

    table.fields td {
        border-bottom: 1pt solid #e7e5e4;
        padding: 6pt 4pt;
        vertical-align: top;
    }

    table.fields td.label {
        width: 35%;
        color: #57534e;
        font-size: 10pt;
    }

    table.fields td.value {
        font-size: 11pt;
    }

    table.value-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 10pt;
    }

    table.value-table th, table.value-table td {
        border: 1pt solid #e7e5e4;
        padding: 4pt 6pt;
        text-align: right;
    }

    table.value-table th {
        background: #f4f4f3;
        font-weight: bold;
        color: #57534e;
    }

    .document-body {
        margin-bottom: 18pt;
        font-size: 11pt;
        line-height: 1.9;
    }

    .document-body p {
        margin: 0 0 10pt;
    }

    h2.section {
        font-size: 12pt;
        font-weight: bold;
        margin: 0 0 6pt;
    }

    table.actions {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 14pt;
    }

    table.actions td {
        border-bottom: 1pt solid #e7e5e4;
        padding: 5pt 4pt;
        font-size: 10.5pt;
        vertical-align: top;
    }

    .doc-footer {
        margin-top: 20pt;
        padding-top: 8pt;
        border-top: 1pt solid #e7e5e4;
        font-size: 8pt;
        color: #a8a29e;
        text-align: center;
    }
</style>
</head>
<body>
<div class="Section1">
    <div class="letterhead">
        @if ($company = $submission->company())
            <p class="company-name">{{ $company->name }}</p>
            @if ($company->national_id || $company->address)
                <p class="company-meta">
                    @if ($company->national_id)
                        شناسه ملی: {{ $company->national_id }}
                    @endif
                    @if ($company->address)
                        — {{ $company->address }}
                    @endif
                </p>
            @endif
        @endif
        <p class="doc-tag">
            شمارهٔ پیگیری: {{ $submission->reference_code }} —
            {{ $submission->submitted_at?->toJalali('Y/m/d H:i') }}
        </p>
    </div>

    <h1 class="form-title">{{ $submission->formTemplate->title }}</h1>
    <p class="sub-meta">
        {{ $submission->department?->name ?? 'بدون دپارتمان' }} —
        ثبت‌کننده: {{ $submission->submitter?->name ?? '—' }}
    </p>
    <p class="status">وضعیت: {{ $submission->status->getLabel() }}</p>

    @php $mergedDocument = $submission->mergedDocumentBody(); @endphp
    @if ($mergedDocument)
        {{-- The form template has a "متن سند" - fixed boilerplate text with
            the real answers merged into it - so render that instead of the
            generic label/value table below. --}}
        <div class="document-body">{!! $mergedDocument !!}</div>
    @else
        <table class="fields">
            {{-- ->withTrashed(): show fields even if removed from the form
                builder since this was submitted - they're soft-deleted, not
                gone, precisely so this historical record stays accurate. --}}
            @foreach ($submission->formTemplate->fields()->withTrashed()->get() as $field)
                @php $value = $submission->values->firstWhere('form_field_id', $field->id); @endphp
                <tr>
                    <td class="label">{{ $field->label }}</td>
                    <td class="value">
                        @if (! $value)
                            —
                        @elseif ($field->field_type->value === 'checkbox')
                            {{ $value->value ? 'بله' : 'خیر' }}
                        @elseif ($field->field_type->value === 'date_jalali')
                            {{ optional($value->value)->toJalali('Y/m/d') ?? '—' }}
                        @elseif ($field->field_type->value === 'signature')
                            <img src="{{ $value->value }}" alt="امضا" height="70">
                        @elseif ($field->field_type->value === 'gps' && is_array($value->value) && isset($value->value['lat'], $value->value['lng']))
                            {{ \Illuminate\Support\Number::toPersian($value->value['lat']) }}, {{ \Illuminate\Support\Number::toPersian($value->value['lng']) }}
                            {{-- Word doesn't run the JS a live/static map would need (same
                            reason the QR code above is skipped here) - a plain hyperlink
                            to Google Maps is the one thing that's both real and reliable
                            in an opened Word document. --}}
                            <br><a href="https://www.google.com/maps?q={{ $value->value['lat'] }},{{ $value->value['lng'] }}">مشاهده روی نقشه</a>
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
                                                <td>{{ isset($row[$column['key']]) ? \Illuminate\Support\Number::toPersian($row[$column['key']]) : '—' }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @else
                            {{ \Illuminate\Support\Number::toPersian($value->value) }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>
    @endif

    @if ($instance = $submission->workflowInstance)
        <h2 class="section">روند تأیید</h2>
        <table class="actions">
            @forelse ($instance->actions as $action)
                <tr>
                    <td>
                        <strong>{{ $action->action->getLabel() }}</strong> — {{ $action->actor->name }}
                        ({{ $action->acted_at->toJalali('Y/m/d H:i') }})
                        @if ($action->comment)
                            <br><span style="color:#57534e;font-size:9.5pt;">{{ $action->comment }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td>هنوز اقدامی ثبت نشده است.</td></tr>
            @endforelse
        </table>
    @endif

    <p class="doc-footer">این سند توسط سامانهٔ گنج‌نامه تولید شده است — {{ now()->toJalali('Y/m/d H:i') }}</p>
</div>
</body>
</html>
