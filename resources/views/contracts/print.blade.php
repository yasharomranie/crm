<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $contract->title }}</title>
    <style>
        :root {
            color-scheme: light;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 2rem 1.25rem;
            background: #f4f4f5;
            font-family: 'Vazirmatn', Tahoma, 'B Nazanin', Arial, sans-serif;
            color: #18181b;
        }

        .toolbar {
            max-width: 48rem;
            margin: 0 auto 1rem;
            display: flex;
            justify-content: flex-end;
            gap: .5rem;
        }

        .toolbar button, .toolbar a {
            font-family: inherit;
            font-size: .875rem;
            border-radius: .5rem;
            padding: .5rem 1rem;
            border: 1px solid #d4d4d8;
            background: #fff;
            color: #18181b;
            cursor: pointer;
            text-decoration: none;
        }

        .toolbar button.primary {
            background: #18181b;
            color: #fff;
            border-color: #18181b;
        }

        .sheet {
            max-width: 48rem;
            margin: 0 auto;
            background: #fff;
            border-radius: .75rem;
            box-shadow: 0 1px 3px rgb(0 0 0 / .1);
            padding: 3rem 3.5rem;
            line-height: 2;
        }

        .sheet h1 {
            font-size: 1.35rem;
            text-align: center;
            margin: 0 0 1.5rem;
        }

        .meta {
            font-size: .8rem;
            color: #71717a;
            text-align: center;
            margin-bottom: 2rem;
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
            border-top: 1px solid #e4e4e7;
            margin-block: 1.5rem;
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
        <button type="button" class="primary" onclick="window.print()">چاپ / ذخیرهٔ PDF</button>
    </div>

    <div class="sheet">
        <h1>{{ $contract->title }}</h1>
        <p class="meta">
            {{ $contract->company?->name ?? 'کارفرما نامشخص' }} × {{ $contract->counterparty_name }}
            — {{ $contract->created_at?->format('Y/m/d') }}
        </p>

        <div class="contract-body">
            {!! Illuminate\Support\Str::markdown($contract->body) !!}
        </div>
    </div>
</body>
</html>
