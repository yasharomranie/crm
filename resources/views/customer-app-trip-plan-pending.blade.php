<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <title>برنامه سفر همدان — گنجنامه</title>

    @if ($plan->isBeingBuilt())
        <meta http-equiv="refresh" content="6">
    @endif

    @include('partials.customer-app-pwa-head')

    @fonts

    @vite(['resources/css/app.css'])

    <style>
        :root {
            color-scheme: light;
            --orange: #f97316;
            --orange-dark: #c2410c;
            --orange-soft: #ffedd5;
            --red: #b91c1c;
            --red-soft: #fee2e2;
            --ink: #1c1917;
            --ink-soft: #57534e;
            --paper: #fff7ed;
            --line: #f1e4d3;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100dvh;
            font-family: 'Vazirmatn', ui-sans-serif, system-ui, sans-serif;
            background: var(--paper);
            color: var(--ink);
        }

        .shell {
            max-width: 30rem;
            margin: 0 auto;
            min-height: 100dvh;
            background: #fff;
            display: flex;
            flex-direction: column;
        }

        .topbar {
            padding: max(1rem, env(safe-area-inset-top)) 1rem .8rem;
            border-bottom: 1px solid var(--line);
            display: flex;
            align-items: center;
            gap: .7rem;
        }

        .topbar a { color: var(--ink-soft); text-decoration: none; font-size: 1.1rem; }
        .topbar h1 { font-size: .95rem; margin: 0; }

        .content {
            flex: 1;
            padding: 2.5rem 1.2rem 6rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
            text-align: center;
        }

        .spinner {
            width: 2.6rem;
            height: 2.6rem;
            border: 4px solid var(--orange-soft);
            border-top-color: var(--orange);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }
        @media (prefers-reduced-motion: reduce) { .spinner { animation: none; } }

        h2 { margin: 0; font-size: 1.05rem; }
        p { margin: 0; font-size: .85rem; line-height: 1.9; color: var(--ink-soft); }

        .failed {
            background: var(--red-soft);
            color: var(--red);
            border-radius: .75rem;
            padding: .8rem 1rem;
            font-size: .85rem;
        }

        .button {
            display: inline-block;
            background: var(--orange);
            color: #fff;
            border-radius: .75rem;
            padding: .7rem 1.4rem;
            font-size: .9rem;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="shell">
        <header class="topbar">
            <a href="{{ route('customer-app.trip-planner') }}">→</a>
            <h1>برنامه سفر {{ $plan->days }} روزه همدان</h1>
        </header>

        <main class="content has-bottom-nav">
            @if ($plan->isBeingBuilt())
                <div class="spinner" role="status" aria-label="در حال ساخت"></div>
                <h2>برنامه شما در حال ساخت است</h2>
                <p>دستیار هوشمند در حال چیدن بهترین مسیر برای شماست. این صفحه خودکار به‌روز می‌شود؛ می‌توانید از آن بیرون بروید و بعداً از «برنامه‌های قبلی من» سر بزنید.</p>
            @else
                <h2>ساخت برنامه کامل نشد</h2>
                <p class="failed">{{ $plan->error_message ?: 'ساخت برنامه با مشکل روبه‌رو شد. لطفاً دوباره تلاش کنید.' }}</p>
                <a href="{{ route('customer-app.trip-planner') }}" class="button">ساخت دوباره</a>
            @endif
        </main>
    </div>

    @include('partials.customer-app-bottom-nav')
</body>
</html>
