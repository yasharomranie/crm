<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    {{-- Self-hosted via Vite's font-bundling (vite.config.js), not Google
        Fonts - this app is hosted for users in Iran, where that CDN can be
        slow or filtered. See LocalViteFontProvider's doc comment for the
        matching fix on the admin panel side. --}}
    @fonts('vazirmatn')
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
            max-width: 64rem;
            margin: 0 auto 1rem;
            display: flex;
            justify-content: flex-end;
        }

        .toolbar button {
            font-family: inherit;
            font-size: .875rem;
            font-weight: 500;
            border-radius: .5rem;
            padding: .55rem 1.1rem;
            border: 1px solid var(--gold);
            background: var(--gold);
            color: #fff;
            cursor: pointer;
        }

        .sheet {
            max-width: 64rem;
            margin: 0 auto;
            background: #fff;
            border-radius: .75rem;
            box-shadow: 0 1px 3px rgb(0 0 0 / .1);
            padding: 2.5rem 2.5rem;
        }

        .letterhead {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            padding-bottom: 1.25rem;
            border-bottom: 2px solid var(--ink);
            margin-bottom: 1.5rem;
        }

        .letterhead .app-name {
            font-size: 1.05rem;
            font-weight: 700;
            margin: 0 0 .3rem;
            color: var(--ink-soft);
        }

        h1.report-title {
            font-size: 1.25rem;
            font-weight: 800;
            margin: 0;
        }

        .letterhead .meta {
            flex-shrink: 0;
            text-align: left;
            font-size: .75rem;
            color: var(--ink-faint);
            line-height: 1.8;
            white-space: nowrap;
        }

        table.report {
            width: 100%;
            border-collapse: collapse;
            font-size: .85rem;
        }

        table.report th, table.report td {
            border-bottom: 1px solid var(--line);
            padding: .6rem .5rem;
            text-align: right;
            vertical-align: top;
        }

        table.report th {
            font-size: .75rem;
            font-weight: 700;
            color: var(--ink-soft);
            background: #fafaf9;
            white-space: nowrap;
        }

        table.report td {
            font-size: .85rem;
        }

        .empty {
            padding: 3rem 0;
            text-align: center;
            color: var(--ink-faint);
            font-size: .9rem;
        }

        footer.doc-footer {
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid var(--line);
            font-size: .7rem;
            color: var(--ink-faint);
            text-align: center;
        }

        @media (max-width: 640px) {
            .sheet {
                padding: 1.5rem 1.25rem;
                overflow-x: auto;
            }

            .letterhead {
                flex-direction: column;
            }

            .letterhead .meta {
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
        <button type="button" onclick="window.print()">چاپ / دانلود PDF</button>
    </div>

    <div class="sheet">
        <div class="letterhead">
            <div>
                <p class="app-name">{{ config('app.name') }}</p>
                <h1 class="report-title">{{ $title }}</h1>
            </div>
            <div class="meta">
                تهیه‌کننده: {{ $generatedBy ?? '—' }}<br>
                تاریخ تهیه: {{ now()->toJalali('Y/m/d H:i') }}<br>
                تعداد ردیف: {{ \Illuminate\Support\Number::toPersian(number_format($rows->count())) }}
            </div>
        </div>

        @if ($rows->isEmpty())
            <p class="empty">داده‌ای برای نمایش با فیلترهای فعلی یافت نشد.</p>
        @else
            <table class="report">
                <thead>
                    <tr>
                        @foreach ($labels as $label)
                            <th>{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            @foreach ($row as $value)
                                <td>{{ $value ?? '—' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <footer class="doc-footer">این گزارش توسط {{ config('app.name') }} تولید شده است — {{ now()->toJalali('Y/m/d H:i') }}</footer>
    </div>
</body>
</html>
