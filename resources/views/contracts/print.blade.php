<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $contract->title }}</title>
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
            display: inline-flex;
            align-items: center;
            gap: .4rem;
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
            margin-bottom: 1.75rem;
        }

        .letterhead .company-name {
            font-size: 1.4rem;
            font-weight: 800;
            margin: 0 0 .3rem;
        }

        .letterhead .company-meta {
            font-size: .78rem;
            color: var(--ink-soft);
            line-height: 1.7;
        }

        .letterhead .doc-tag {
            flex-shrink: 0;
            text-align: left;
            font-size: .72rem;
            color: var(--ink-faint);
            line-height: 1.8;
            white-space: nowrap;
        }

        h1.title {
            font-size: 1.3rem;
            font-weight: 800;
            text-align: center;
            margin: 0 0 .75rem;
        }

        .parties {
            display: flex;
            justify-content: center;
            gap: .6rem;
            flex-wrap: wrap;
            font-size: .88rem;
            color: var(--ink-soft);
            text-align: center;
            margin-bottom: 2rem;
        }

        .parties .vs {
            color: var(--ink-faint);
        }

        .parties strong {
            color: var(--ink);
            font-weight: 700;
        }

        /* ---------- Body ---------- */

        .contract-body {
            line-height: 2.1;
            font-size: .95rem;
        }

        .contract-body :where(h1, h2, h3) {
            font-weight: 700;
            margin-block: 1.25rem .5rem;
        }

        .contract-body h2 {
            font-size: 1.05rem;
        }

        .contract-body p {
            margin-block: .6rem;
        }

        .contract-body ul, .contract-body ol {
            margin-inline-start: 1.5rem;
        }

        .contract-body strong {
            font-weight: 700;
        }

        .contract-body hr {
            border: none;
            border-top: 1px solid var(--line);
            margin-block: 1.5rem;
        }

        /* ---------- Signatures ---------- */

        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-top: 3.5rem;
            padding-top: 1.5rem;
        }

        .signature-box {
            text-align: center;
        }

        .signature-box .role {
            font-size: .8rem;
            font-weight: 700;
            color: var(--ink-soft);
            margin-bottom: .3rem;
        }

        .signature-box .who {
            font-size: .85rem;
            margin-bottom: 2.75rem;
        }

        .signature-box .line {
            border-top: 1px solid var(--ink-faint);
            padding-top: .4rem;
            font-size: .74rem;
            color: var(--ink-faint);
        }

        footer.doc-footer {
            margin-top: 2.5rem;
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

            .signatures {
                grid-template-columns: 1fr;
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
        <a href="{{ route('contracts.export-word', $contract) }}">دانلود Word</a>
        <button type="button" class="primary" onclick="window.print()">چاپ / دانلود PDF</button>
    </div>

    <div class="sheet">
        <div class="letterhead">
            <div>
                <p class="company-name">{{ $contract->company?->name ?? 'شرکت نامشخص' }}</p>
                @if ($contract->company)
                    <p class="company-meta">
                        @if ($contract->company->national_id)
                            شناسه ملی: {{ $contract->company->national_id }}
                        @endif
                        @if ($contract->company->address)
                            <br>{{ $contract->company->address }}
                        @endif
                    </p>
                @endif
            </div>
            <div class="doc-tag">
                شمارهٔ داخلی: #{{ str_pad($contract->id, 5, '0', STR_PAD_LEFT) }}<br>
                تاریخ تنظیم: {{ $contract->created_at?->toJalali('Y/m/d') }}
            </div>
        </div>

        <h1 class="title">{{ $contract->title }}</h1>
        <p class="parties">
            <strong>{{ $contract->company?->name ?? 'کارفرما نامشخص' }}</strong> (کارفرما)
            <span class="vs">×</span>
            <strong>{{ $contract->counterparty_name }}</strong> (طرف مقابل)
        </p>

        <div class="contract-body">
            {!! Illuminate\Support\Str::markdown($contract->body) !!}
        </div>

        <div class="signatures">
            <div class="signature-box">
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
            </div>
            <div class="signature-box">
                <p class="role">امضای طرف مقابل</p>
                <p class="who">{{ $contract->counterparty_name }}</p>
                <p class="line">نام، امضا و تاریخ</p>
            </div>
        </div>

        <footer class="doc-footer">
            این سند توسط سامانهٔ گنج‌نامه تولید شده است — {{ now()->toJalali('Y/m/d H:i') }}
        </footer>
    </div>
</body>
</html>
