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
            {{ $submission->submitted_at?->format('Y/m/d H:i') }}
        </p>
    </div>

    <h1 class="form-title">{{ $submission->formTemplate->title }}</h1>
    <p class="sub-meta">
        {{ $submission->department?->name ?? 'بدون دپارتمان' }} —
        ثبت‌کننده: {{ $submission->submitter?->name ?? '—' }}
    </p>
    <p class="status">وضعیت: {{ $submission->status->getLabel() }}</p>

    <table class="fields">
        @foreach ($submission->formTemplate->fields as $field)
            @php $value = $submission->values->firstWhere('form_field_id', $field->id); @endphp
            <tr>
                <td class="label">{{ $field->label }}</td>
                <td class="value">
                    @if (! $value)
                        —
                    @elseif ($field->field_type->value === 'checkbox')
                        {{ $value->value ? 'بله' : 'خیر' }}
                    @elseif ($field->field_type->value === 'signature')
                        <img src="{{ $value->value }}" alt="امضا" height="70">
                    @elseif ($field->field_type->value === 'gps' && is_array($value->value))
                        {{ $value->value['lat'] ?? '—' }}, {{ $value->value['lng'] ?? '—' }}
                    @elseif ($field->field_type->value === 'file')
                        پیوست ثبت شده
                    @else
                        {{ $value->value }}
                    @endif
                </td>
            </tr>
        @endforeach
    </table>

    @if ($instance = $submission->workflowInstance)
        <h2 class="section">روند تأیید</h2>
        <table class="actions">
            @forelse ($instance->actions as $action)
                <tr>
                    <td>
                        <strong>{{ $action->action->getLabel() }}</strong> — {{ $action->actor->name }}
                        ({{ $action->acted_at->format('Y/m/d H:i') }})
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

    <p class="doc-footer">این سند توسط سامانهٔ گنج‌نامه تولید شده است — {{ now()->format('Y/m/d H:i') }}</p>
</div>
</body>
</html>
