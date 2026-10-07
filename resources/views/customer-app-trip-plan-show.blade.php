<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <title>برنامه سفر همدان — گنجنامه</title>

    @include('partials.customer-app-pwa-head')

    @fonts

    @vite(['resources/css/app.css'])

    <style>
        :root {
            color-scheme: light;
            --orange: #f97316;
            --orange-dark: #c2410c;
            --orange-soft: #ffedd5;
            --green: #15803d;
            --green-soft: #dcfce7;
            --ink: #1c1917;
            --ink-soft: #57534e;
            --ink-faint: #a8a29e;
            --paper: #fff7ed;
            --line: #f1e4d3;
        }

        * { box-sizing: border-box; }
        html, body { height: 100%; }

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
            box-shadow: 0 0 40px rgb(0 0 0 / .05);
            display: flex;
            flex-direction: column;
        }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 10;
            background: #fff;
            padding: max(1rem, env(safe-area-inset-top)) 1rem .8rem;
            border-bottom: 1px solid var(--line);
            display: flex;
            align-items: center;
            gap: .7rem;
        }

        .topbar a.back {
            color: var(--ink-faint);
            text-decoration: none;
            font-size: 1.1rem;
        }

        .topbar h1 {
            font-size: .95rem;
            margin: 0;
        }

        .content {
            flex: 1;
            padding: 1rem 1rem 3rem;
            display: flex;
            flex-direction: column;
            gap: 1.2rem;
        }

        .flash {
            background: var(--green-soft);
            color: var(--green);
            border-radius: .75rem;
            padding: .7rem .9rem;
            font-size: .82rem;
        }

        .overview-card {
            border-radius: 1.2rem;
            padding: 1.2rem;
            background: linear-gradient(145deg, var(--orange), var(--orange-dark) 75%);
            color: #fff;
            position: relative;
            overflow: hidden;
        }

        .overview-card::after {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 90% 0%, rgb(255 255 255 / .2), transparent 55%);
        }

        .overview-card .badge {
            position: relative;
            display: inline-block;
            font-size: .7rem;
            font-weight: 700;
            background: rgb(255 255 255 / .2);
            padding: .25rem .7rem;
            border-radius: 1rem;
            margin-bottom: .6rem;
        }

        .overview-card p {
            position: relative;
            margin: 0;
            font-size: .85rem;
            line-height: 1.8;
        }

        .day-tabs {
            display: flex;
            gap: .5rem;
            overflow-x: auto;
            padding-bottom: .2rem;
        }

        .day-tabs button {
            flex-shrink: 0;
            appearance: none;
            border: 1px solid var(--line);
            background: var(--paper);
            color: var(--ink-soft);
            font-family: inherit;
            font-size: .8rem;
            font-weight: 700;
            padding: .55rem 1.15rem;
            border-radius: 2rem;
            cursor: pointer;
        }

        .day-tabs button[aria-selected="true"] {
            background: var(--orange);
            border-color: var(--orange);
            color: #fff;
        }

        .day-panel {
            display: none;
        }

        .day-panel[data-open="true"] {
            display: block;
        }

        .stop {
            display: grid;
            grid-template-columns: 3rem 1.3rem 1fr;
        }

        .stop-time {
            padding-top: .4rem;
            font-size: .76rem;
            font-weight: 800;
            color: var(--orange-dark);
            text-align: center;
            white-space: nowrap;
        }

        .stop-line {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .stop-line .dot {
            width: .65rem;
            height: .65rem;
            border-radius: 50%;
            background: var(--orange);
            margin-top: .5rem;
            flex-shrink: 0;
            box-shadow: 0 0 0 3px var(--orange-soft);
        }

        .stop-line .bar {
            flex: 1;
            width: 2px;
            background: var(--line);
            margin-top: .3rem;
        }

        .stop:last-child .stop-line .bar {
            display: none;
        }

        .stop-card {
            border: 1px solid var(--line);
            border-radius: 1rem;
            overflow: hidden;
            margin-bottom: 1.2rem;
            background: #fff;
        }

        .stop-card img {
            width: 100%;
            height: 8rem;
            object-fit: cover;
            display: block;
            background: var(--paper);
        }

        .stop-body {
            padding: .9rem 1rem;
        }

        .stop-category {
            display: inline-block;
            font-size: .66rem;
            font-weight: 700;
            color: var(--orange-dark);
            background: var(--orange-soft);
            padding: .18rem .6rem;
            border-radius: 1rem;
            margin-bottom: .45rem;
        }

        .stop-body h4 {
            margin: 0 0 .3rem;
            font-size: .92rem;
        }

        .stop-duration {
            margin: 0 0 .5rem;
            font-size: .74rem;
            color: var(--ink-faint);
        }

        .stop-note {
            margin: 0 0 .75rem;
            font-size: .82rem;
            color: var(--ink-soft);
            line-height: 1.7;
        }

        .stop-directions {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            font-size: .78rem;
            font-weight: 700;
            color: #fff;
            background: var(--green);
            padding: .5rem .95rem;
            border-radius: .7rem;
            text-decoration: none;
        }

        .stop-directions svg {
            width: 15px;
            height: 15px;
        }

        .empty {
            text-align: center;
            color: var(--ink-faint);
            font-size: .82rem;
            padding: 1.5rem 0;
        }
    </style>
</head>
<body>
    <div class="shell">
        <header class="topbar">
            <a href="{{ route('customer-app.trip-planner') }}" class="back">→</a>
            <h1>برنامه سفر {{ $plan->days }} روزه همدان</h1>
        </header>

        <main class="content has-bottom-nav">
            @if (session('success'))
                <div class="flash">{{ session('success') }}</div>
            @endif

            @if ($plan->overview)
                <section class="overview-card">
                    <span class="badge">معرفی برنامه</span>
                    <p>{{ $plan->overview }}</p>
                </section>
            @endif

            @if ($itemsByDay->count() > 1)
                <div class="day-tabs" role="tablist">
                    @foreach ($itemsByDay as $dayNumber => $items)
                        <button type="button" data-day-tab="{{ $dayNumber }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">روز {{ $dayNumber }}</button>
                    @endforeach
                </div>
            @endif

            @forelse ($itemsByDay as $dayNumber => $items)
                <div class="day-panel" data-day-panel="{{ $dayNumber }}" data-open="{{ $loop->first ? 'true' : 'false' }}">
                    @foreach ($items as $item)
                        <div class="stop">
                            <div class="stop-time">{{ substr((string) $item->start_time, 0, 5) }}</div>
                            <div class="stop-line">
                                <span class="dot"></span>
                                <span class="bar"></span>
                            </div>
                            <div class="stop-card">
                                @if ($item->attraction->imageUrl())
                                    <img src="{{ $item->attraction->imageUrl() }}" alt="">
                                @endif
                                <div class="stop-body">
                                    <span class="stop-category">{{ $item->attraction->category->getLabel() }}</span>
                                    <h4>{{ $item->attraction->name }}</h4>
                                    <p class="stop-duration">
                                        {{ substr((string) $item->start_time, 0, 5) }} تا {{ substr((string) $item->end_time, 0, 5) }}
                                        @if ($item->attraction->address)
                                            · {{ $item->attraction->address }}
                                        @endif
                                    </p>
                                    @if ($item->note)
                                        <p class="stop-note">{{ $item->note }}</p>
                                    @endif
                                    <a href="{{ $item->attraction->directionsUrl() }}" target="_blank" rel="noopener" class="stop-directions">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12 3 3l6 9-6 9 18-9Z"/></svg>
                                        مسیریابی
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @empty
                <p class="empty">برنامه‌ای برای نمایش وجود ندارد.</p>
            @endforelse
        </main>
    </div>

    @include('partials.customer-app-bottom-nav')

    <script>
        (function () {
            var tabs = document.querySelectorAll('[data-day-tab]');
            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    tabs.forEach(function (t) { t.setAttribute('aria-selected', 'false'); });
                    tab.setAttribute('aria-selected', 'true');
                    document.querySelectorAll('[data-day-panel]').forEach(function (panel) {
                        panel.setAttribute('data-open', panel.dataset.dayPanel === tab.dataset.dayTab ? 'true' : 'false');
                    });
                });
            });
        })();
    </script>
</body>
</html>
