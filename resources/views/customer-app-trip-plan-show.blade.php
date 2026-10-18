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

        /* --- editing, sharing, map --------------------------------------- */
        .toolbar { display: flex; flex-wrap: wrap; gap: .45rem; }
        .toolbar a, .toolbar button, .mini-btn {
            font: inherit; font-size: .76rem; font-weight: 600; cursor: pointer;
            padding: .42rem .7rem; border-radius: 999px; border: 1px solid var(--line);
            background: #fff; color: var(--ink-soft); text-decoration: none;
        }
        .toolbar .primary { background: var(--orange); border-color: var(--orange); color: #fff; }
        .errors-box { background: #fee2e2; color: #b91c1c; padding: .6rem .8rem; border-radius: .6rem; font-size: .82rem; margin: 0; }
        .stop-tools { display: flex; gap: .3rem; margin-top: .5rem; flex-wrap: wrap; }
        .stop-tools form { margin: 0; }
        .mini-btn { padding: .2rem .55rem; font-size: .72rem; }
        .mini-btn.danger { color: #b91c1c; border-color: #fecaca; }
        .stop-meta { display: flex; flex-wrap: wrap; gap: .3rem; margin: .35rem 0 0; }
        .stop-meta span { font-size: .68rem; background: var(--paper); border: 1px solid var(--line); border-radius: 999px; padding: .1rem .5rem; color: var(--ink-soft); }
        .travel-note { font-size: .72rem; color: var(--ink-faint); margin: .1rem 0 .1rem 0; padding-inline-start: 4.6rem; }
        .day-actions { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; margin-top: .8rem; padding-top: .8rem; border-top: 1px dashed var(--line); }
        .day-actions form { margin: 0; display: flex; gap: .4rem; align-items: center; }
        .day-actions select { font: inherit; font-size: .78rem; padding: .35rem .5rem; border: 1px solid var(--line); border-radius: .5rem; max-width: 12rem; }
        .block { border: 1px solid var(--line); border-radius: .9rem; padding: .9rem; }
        .block h3 { margin: 0 0 .5rem; font-size: .9rem; }
        .block ul { margin: 0; padding-inline-start: 1.1rem; font-size: .82rem; color: var(--ink-soft); }
        .share-box { font-size: .76rem; word-break: break-all; background: var(--paper); padding: .5rem .6rem; border-radius: .5rem; direction: ltr; text-align: left; }
        .rating-row { display: flex; gap: .3rem; flex-wrap: wrap; }
        .rating-row label { cursor: pointer; font-size: .8rem; border: 1px solid var(--line); border-radius: .5rem; padding: .25rem .6rem; }
        .rating-row input { display: none; }
        .rating-row input:checked + label { background: var(--orange); border-color: var(--orange); color: #fff; }
        #trip-map { height: 16rem; border-radius: .8rem; border: 1px solid var(--line); }
        details.map-wrap summary { cursor: pointer; font-size: .85rem; font-weight: 700; margin-bottom: .5rem; }
        .cta-row { display: flex; gap: .5rem; flex-wrap: wrap; }
        .cta-row a { flex: 1; text-align: center; padding: .65rem .8rem; border-radius: .7rem; background: var(--orange); color: #fff; text-decoration: none; font-weight: 700; font-size: .84rem; }
        .cta-row a.alt { background: #fff; color: var(--orange-dark); border: 1px solid var(--orange); }
        .rating-form textarea { width: 100%; font: inherit; font-size: .8rem; border: 1px solid var(--line); border-radius: .5rem; padding: .4rem; margin-top: .5rem; }
        @media print {
            .toolbar, .stop-tools, .day-actions, .rating-form, .bottom-nav, .topbar a.back, .cta-row, .no-print, details.map-wrap { display: none !important; }
            .day-panel { display: block !important; }
            .shell { box-shadow: none; max-width: none; }
        }
    </style>
</head>
<body>
    <div class="shell">
        <header class="topbar">
            @unless ($readonly)<a href="{{ route('customer-app.trip-planner') }}" class="back">→</a>@endunless
            <h1>برنامه سفر {{ $plan->days }} روزه همدان</h1>
        </header>

        <main class="content has-bottom-nav">
            @if (session('success'))
                <div class="flash">{{ session('success') }}</div>
            @endif
            @if ($errors->has('edit'))
                <p class="errors-box">{{ $errors->first('edit') }}</p>
            @endif

            @if ($plan->overview)
                <section class="overview-card">
                    <span class="badge">معرفی برنامه</span>
                    <p>{{ $plan->overview }}</p>
                </section>
            @endif

            @unless ($readonly)
                <div class="toolbar no-print">
                    <button type="button" class="primary" onclick="window.print()">چاپ / ذخیره PDF</button>
                    <a href="{{ route('customer-app.trip-planner.calendar', $plan) }}">افزودن به تقویم</a>
                    @if ($shareUrl)
                        <form method="POST" action="{{ route('customer-app.trip-planner.unshare', $plan) }}" style="margin:0">@csrf @method('DELETE')<button type="submit">قطع اشتراک</button></form>
                    @else
                        <form method="POST" action="{{ route('customer-app.trip-planner.share', $plan) }}" style="margin:0">@csrf<button type="submit">ساخت لینک اشتراک</button></form>
                    @endif
                </div>
                @if ($shareUrl)
                    <div class="block no-print">
                        <h3>لینک اشتراک</h3>
                        <div class="share-box">{{ $shareUrl }}</div>
                        <p style="margin:.5rem 0 0"><a href="https://wa.me/?text={{ urlencode('برنامه سفر همدان من: '.$shareUrl) }}" target="_blank" rel="noopener" class="mini-btn" style="text-decoration:none">ارسال با واتساپ</a></p>
                    </div>
                @endif
            @endunless

            @if ($itemsByDay->count() > 1)
                <div class="day-tabs" role="tablist">
                    @foreach ($itemsByDay as $dayNumber => $items)
                        <button type="button" data-day-tab="{{ $dayNumber }}" aria-selected="{{ $dayNumber == $activeDay ? 'true' : 'false' }}">روز {{ $dayNumber }}</button>
                    @endforeach
                </div>
            @endif

            @forelse ($itemsByDay as $dayNumber => $items)
                <div class="day-panel" data-day-panel="{{ $dayNumber }}" data-open="{{ $dayNumber == $activeDay ? 'true' : 'false' }}">
                    @foreach ($items as $item)
                        @if ($item->travel_minutes)
                            <p class="travel-note">↓ حدود {{ $item->travel_minutes }} دقیقه تا این مقصد</p>
                        @endif
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
                                    <div class="stop-meta">
                                        @if ($item->attraction->entry_fee !== null)
                                            <span>{{ $item->attraction->entry_fee > 0 ? 'ورودی '.number_format($item->attraction->entry_fee).' تومان' : 'ورودی رایگان' }}</span>
                                        @endif
                                        @if ($item->attraction->kid_friendly)<span>مناسب کودک</span>@endif
                                        @if ($item->attraction->senior_friendly)<span>مناسب سالمند</span>@endif
                                        @if ($item->attraction->has_parking)<span>پارکینگ دارد</span>@endif
                                        @if ($item->attraction->phone)<span>☎ {{ $item->attraction->phone }}</span>@endif
                                    </div>
                                    @if ($item->note)
                                        <p class="stop-note">{{ $item->note }}</p>
                                    @endif
                                    <a href="{{ $item->attraction->directionsUrl() }}" target="_blank" rel="noopener" class="stop-directions">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12 3 3l6 9-6 9 18-9Z"/></svg>
                                        مسیریابی
                                    </a>
                                    @if ($item->attraction->product_id && ! $readonly)
                                        <a href="{{ route('customer-app.tickets') }}" class="stop-directions" style="margin-inline-start:.6rem">خرید بلیت</a>
                                    @endif
                                    @unless ($readonly)
                                        <div class="stop-tools no-print">
                                            @unless ($loop->first)
                                                <form method="POST" action="{{ route('customer-app.trip-planner.items.move', [$plan, $item]) }}">@csrf<input type="hidden" name="direction" value="up"><button class="mini-btn" type="submit">↑ بالاتر</button></form>
                                            @endunless
                                            @unless ($loop->last)
                                                <form method="POST" action="{{ route('customer-app.trip-planner.items.move', [$plan, $item]) }}">@csrf<input type="hidden" name="direction" value="down"><button class="mini-btn" type="submit">↓ پایین‌تر</button></form>
                                            @endunless
                                            <form method="POST" action="{{ route('customer-app.trip-planner.items.destroy', [$plan, $item]) }}">@csrf @method('DELETE')<button class="mini-btn danger" type="submit">حذف</button></form>
                                        </div>
                                    @endunless
                                </div>
                            </div>
                        </div>
                    @endforeach

                    @unless ($readonly)
                        <div class="day-actions no-print">
                            <form method="POST" action="{{ route('customer-app.trip-planner.days.regenerate', [$plan, $dayNumber]) }}">@csrf<button class="mini-btn" type="submit">بازسازی این روز</button></form>
                            @if ($addableAttractions->isNotEmpty())
                                <form method="POST" action="{{ route('customer-app.trip-planner.items.store', $plan) }}">
                                    @csrf
                                    <input type="hidden" name="day_number" value="{{ $dayNumber }}">
                                    <select name="attraction_id" required aria-label="افزودن مکان">
                                        @foreach ($addableAttractions as $option)
                                            <option value="{{ $option->id }}">{{ $option->name }}</option>
                                        @endforeach
                                    </select>
                                    <button class="mini-btn" type="submit">افزودن به این روز</button>
                                </form>
                            @endif
                        </div>
                    @endunless
                </div>
            @empty
                <p class="empty">برنامه‌ای برای نمایش وجود ندارد.</p>
            @endforelse

            @if ($events->isNotEmpty())
                <section class="block">
                    <h3>رویدادهای همین روزها در همدان</h3>
                    <ul>
                        @foreach ($events as $event)
                            <li>{{ $event->title }} · {{ $event->starts_at->toJalali('Y/m/d H:i') }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @php
                $mapStops = $itemsByDay->flatten(1)->filter(fn ($i) => $i->attraction->lat && $i->attraction->lng)->values();
                $mapStopsPayload = $mapStops
                    ->map(fn ($i) => [
                        'lat' => (float) $i->attraction->lat,
                        'lng' => (float) $i->attraction->lng,
                        'name' => $i->attraction->name,
                        'day' => $i->day_number,
                    ])
                    ->all();
            @endphp
            @if ($mapStops->isNotEmpty())
                <details class="map-wrap" open>
                    <summary>نقشه‌ی توقف‌ها</summary>
                    <div id="trip-map"></div>
                </details>
            @endif

            @unless ($readonly)
                <section class="block no-print">
                    <h3>ادامه‌ی سفر</h3>
                    <div class="cta-row">
                        <a href="{{ route('customer-app.tickets') }}">خرید بلیت جاذبه‌ها</a>
                        <a class="alt" href="{{ route('customer-app.bookings', array_filter(['check_in_date' => $bookingCheckIn, 'check_out_date' => $bookingCheckOut])) }}">رزرو اقامت</a>
                    </div>
                </section>

                <section class="block rating-form no-print">
                    <h3>این برنامه چطور بود؟</h3>
                    <form method="POST" action="{{ route('customer-app.trip-planner.rate', $plan) }}">
                        @csrf
                        <div class="rating-row">
                            @foreach ([1, 2, 3, 4, 5] as $star)
                                <input type="radio" name="rating" value="{{ $star }}" id="rate-{{ $star }}" {{ $plan->rating === $star ? 'checked' : '' }} required>
                                <label for="rate-{{ $star }}">{{ $star }} ★</label>
                            @endforeach
                        </div>
                        <textarea name="feedback" rows="2" maxlength="1000" placeholder="چه چیزی را دوست نداشتید یا چه چیزی کم بود؟ (اختیاری)">{{ old('feedback', $plan->feedback) }}</textarea>
                        <button class="mini-btn" type="submit" style="margin-top:.5rem">ثبت بازخورد</button>
                    </form>
                </section>
            @endunless
        </main>
    </div>

    @unless ($readonly)
        @include('partials.customer-app-bottom-nav')
    @endunless

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

    @if ($mapStops->isNotEmpty())
        <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
        <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
        <script>
            (function () {
                if (typeof L === 'undefined') { return; }
                var stops = @json($mapStopsPayload);
                var map = L.map('trip-map');
                L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '© OpenStreetMap' }).addTo(map);
                var colors = ['#f97316', '#0ea5e9', '#16a34a', '#a855f7', '#e11d48', '#ca8a04', '#0d9488'];
                var bounds = [];
                var byDay = {};
                stops.forEach(function (stop) {
                    (byDay[stop.day] = byDay[stop.day] || []).push(stop);
                });
                Object.keys(byDay).forEach(function (day) {
                    var color = colors[(day - 1) % colors.length];
                    var points = byDay[day].map(function (stop, index) {
                        var point = [stop.lat, stop.lng];
                        bounds.push(point);
                        L.circleMarker(point, { radius: 8, color: color, fillColor: color, fillOpacity: .9 })
                            .bindTooltip('روز ' + day + ' · ' + (index + 1) + '. ' + stop.name)
                            .addTo(map);
                        return point;
                    });
                    L.polyline(points, { color: color, weight: 3, opacity: .6 }).addTo(map);
                });
                map.fitBounds(bounds, { padding: [24, 24], maxZoom: 15 });
            })();
        </script>
    @endif
</body>
</html>
