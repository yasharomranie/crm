<!doctype html>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="utf-8">
<title>{{ $contract->title }}</title>
{{--
    The classic dependency-free "Word export": Word opens HTML served with
    a .doc filename/MIME type natively, and these two mso-specific blocks
    tell it to treat the page as an actual print-laid-out A4 document
    instead of a generic web page. No PDF/Word-generation library needed.
    Word doesn't fetch our Google Font when opening a local file, so this
    renders in the reader's default Persian font (usually B Nazanin/Tahoma)
    rather than Vazirmatn - expected and fine for a printable contract.
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
        font-size: 13pt;
        line-height: 1.9;
    }

    .letterhead {
        border-bottom: 2pt solid #1c1917;
        padding-bottom: 10pt;
        margin-bottom: 18pt;
    }

    .letterhead .company-name {
        font-size: 16pt;
        font-weight: bold;
        margin: 0 0 4pt;
    }

    .letterhead .company-meta {
        font-size: 10pt;
        color: #57534e;
    }

    .letterhead .doc-tag {
        font-size: 9pt;
        color: #78716c;
        margin-top: 6pt;
    }

    h1.title {
        font-size: 15pt;
        font-weight: bold;
        text-align: center;
        margin: 0 0 6pt;
    }

    p.parties {
        text-align: center;
        font-size: 11pt;
        color: #44403c;
        margin-bottom: 20pt;
    }

    .contract-body h2 {
        font-size: 12.5pt;
        font-weight: bold;
        margin-block: 14pt 4pt;
    }

    .contract-body p {
        margin-block: 6pt;
    }

    .contract-body ul, .contract-body ol {
        margin-inline-start: 20pt;
    }

    table.signatures {
        width: 100%;
        margin-top: 42pt;
        border-collapse: collapse;
    }

    table.signatures td {
        width: 50%;
        text-align: center;
        vertical-align: top;
        padding: 0 10pt;
    }

    table.signatures .role {
        font-size: 10pt;
        font-weight: bold;
        color: #44403c;
    }

    table.signatures .who {
        font-size: 10.5pt;
        margin-bottom: 34pt;
    }

    table.signatures .line {
        border-top: 1pt solid #a8a29e;
        padding-top: 4pt;
        font-size: 9pt;
        color: #78716c;
    }

    .doc-footer {
        margin-top: 30pt;
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
        <p class="company-name">{{ $contract->company?->name ?? 'شرکت نامشخص' }}</p>
        @if ($contract->company)
            <p class="company-meta">
                @if ($contract->company->national_id)
                    شناسه ملی: {{ $contract->company->national_id }}
                @endif
                @if ($contract->company->address)
                    — {{ $contract->company->address }}
                @endif
            </p>
        @endif
        <p class="doc-tag">
            شمارهٔ داخلی: #{{ str_pad($contract->id, 5, '0', STR_PAD_LEFT) }} —
            تاریخ تنظیم: {{ $contract->created_at?->format('Y/m/d') }}
        </p>
    </div>

    <h1 class="title">{{ $contract->title }}</h1>
    <p class="parties">
        <strong>{{ $contract->company?->name ?? 'کارفرما نامشخص' }}</strong> (کارفرما)
        &nbsp;×&nbsp;
        <strong>{{ $contract->counterparty_name }}</strong> (طرف مقابل)
    </p>

    <div class="contract-body">
        {!! Illuminate\Support\Str::markdown($contract->body) !!}
    </div>

    <table class="signatures">
        <tr>
            <td>
                <p class="role">امضای کارفرما</p>
                <p class="who">
                    {{ $contract->company?->name ?? '—' }}
                    @if ($contract->company?->representative_name)
                        <br>{{ $contract->company->representative_name }}
                        @if ($contract->company->representative_role)
                            ({{ $contract->company->representative_role }})
                        @endif
                    @endif
                </p>
                <p class="line">نام، امضا و تاریخ</p>
            </td>
            <td>
                <p class="role">امضای طرف مقابل</p>
                <p class="who">{{ $contract->counterparty_name }}</p>
                <p class="line">نام، امضا و تاریخ</p>
            </td>
        </tr>
    </table>

    <p class="doc-footer">این سند توسط سامانهٔ گنج‌نامه تولید شده است — {{ now()->format('Y/m/d H:i') }}</p>
</div>
</body>
</html>
