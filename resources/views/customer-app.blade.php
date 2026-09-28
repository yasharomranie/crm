<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <title>گنجنامه — بلیت، اقامت و باشگاه مشتریان</title>
    <meta name="description" content="بلیت و چک‌این، رزرو اقامت، کیف پول و امتیاز، کد تخفیف و پشتیبانی مجموعه گنجنامه، همه در یک اپ.">

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

        * {
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
        }

        body {
            margin: 0;
            min-height: 100dvh;
            font-family: 'Vazirmatn', ui-sans-serif, system-ui, sans-serif;
            background: var(--paper);
            color: var(--ink);
            overflow-x: hidden;
        }

        .shell {
            max-width: 30rem;
            margin: 0 auto;
            min-height: 100dvh;
            background: #fff;
            display: flex;
            flex-direction: column;
            box-shadow: 0 0 40px rgb(0 0 0 / .05);
        }

        /* ---------- Top bar ---------- */

        .topbar {
            position: sticky;
            top: 0;
            z-index: 10;
            background: #fff;
            padding: max(.9rem, env(safe-area-inset-top)) 1rem .6rem;
            border-bottom: 1px solid var(--line);
        }

        .search {
            display: flex;
            align-items: center;
            gap: .5rem;
            background: var(--paper);
            border: 1px solid var(--line);
            border-radius: .85rem;
            padding: .55rem .8rem;
        }

        .search svg {
            flex-shrink: 0;
            color: var(--ink-faint);
        }

        .search input {
            border: none;
            background: transparent;
            outline: none;
            font-family: inherit;
            font-size: .88rem;
            color: var(--ink);
            width: 100%;
        }

        .search input::placeholder {
            color: var(--ink-faint);
        }

        .tabs {
            display: flex;
            gap: 1.25rem;
            margin-top: .75rem;
            overflow-x: auto;
        }

        .tab {
            appearance: none;
            border: none;
            background: none;
            font-family: inherit;
            font-size: .82rem;
            color: var(--ink-faint);
            padding: 0 0 .55rem;
            white-space: nowrap;
            cursor: pointer;
            border-bottom: 2px solid transparent;
        }

        .tab[data-active="true"] {
            color: var(--orange-dark);
            font-weight: 700;
            border-bottom-color: var(--orange);
        }

        /* ---------- Scroll area ---------- */

        .content {
            flex: 1;
            padding: 1rem 1rem 6rem;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        /* ---------- Banners ---------- */

        .banners {
            display: flex;
            gap: .75rem;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            margin: 0 -1rem;
            padding: 0 1rem;
        }

        .banner {
            scroll-snap-align: start;
            flex: 0 0 88%;
            border-radius: 1rem;
            overflow: hidden;
            position: relative;
            aspect-ratio: 16 / 8;
            background: linear-gradient(135deg, var(--orange), var(--green));
            color: #fff;
            text-decoration: none;
            display: block;
        }

        .banner img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .banner .caption {
            position: absolute;
            inset-inline: 0;
            bottom: 0;
            padding: .9rem 1rem;
            background: linear-gradient(to top, rgb(0 0 0 / .55), transparent);
        }

        .banner .caption strong {
            display: block;
            font-size: .92rem;
        }

        .banner .caption span {
            display: block;
            font-size: .76rem;
            opacity: .9;
            margin-top: .15rem;
        }

        /* ---------- Sections ---------- */

        .section h2 {
            font-size: .95rem;
            font-weight: 700;
            margin: 0 0 .8rem;
        }

        .tiles {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: .9rem 0;
        }

        .tile {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: .4rem;
            border: none;
            background: none;
            font-family: inherit;
            color: var(--ink);
            text-decoration: none;
            cursor: pointer;
            padding: 0;
        }

        .tile .icon {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .tile .icon img {
            width: 26px;
            height: 26px;
            object-fit: contain;
        }

        .tile .icon svg {
            width: 24px;
            height: 24px;
        }

        .tile .label {
            font-size: .72rem;
            line-height: 1.35;
            text-align: center;
        }

        .empty {
            text-align: center;
            color: var(--ink-faint);
            font-size: .85rem;
            padding: 3rem 1rem;
        }

        /* ---------- Toast (placeholder features) ---------- */

        #toast {
            position: fixed;
            inset-inline: 0;
            bottom: max(1rem, env(safe-area-inset-bottom));
            display: flex;
            justify-content: center;
            pointer-events: none;
            z-index: 1002;
        }

        #toast span {
            background: var(--ink);
            color: #fff;
            font-size: .8rem;
            padding: .55rem 1.1rem;
            border-radius: 2rem;
            opacity: 0;
            transform: translateY(6px);
            transition: opacity .2s ease, transform .2s ease;
        }

        #toast span.is-visible {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <div class="shell">
        <header class="topbar">
            <label class="search">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.2-3.2"/></svg>
                <input type="text" id="tile-search" placeholder="جستجو در امکانات گنجنامه…" autocomplete="off">
            </label>

            <nav class="tabs">
                <button type="button" class="tab" data-toast="سفارش‌های من">سفارش‌های من</button>
                <button type="button" class="tab" data-toast="کیف پول">کیف پول</button>
                <button type="button" class="tab" data-toast="باشگاه">باشگاه</button>
                <button type="button" class="tab" data-active="true">خانه</button>
            </nav>
        </header>

        <main class="content">
            @if ($banners->isNotEmpty())
                <div class="banners">
                    @foreach ($banners as $banner)
                        @php $tag = $banner->link_url ? 'a' : 'div'; @endphp
                        <{{ $tag }} class="banner" @if ($banner->link_url) href="{{ $banner->link_url }}" @endif>
                            <img src="{{ $banner->imageUrl() }}" alt="">
                            @if ($banner->title || $banner->subtitle)
                                <div class="caption">
                                    @if ($banner->title)
                                        <strong>{{ $banner->title }}</strong>
                                    @endif
                                    @if ($banner->subtitle)
                                        <span>{{ $banner->subtitle }}</span>
                                    @endif
                                </div>
                            @endif
                        </{{ $tag }}>
                    @endforeach
                </div>
            @endif

            @forelse ($sections as $section)
                <section class="section" data-section>
                    <h2>{{ $section->title }}</h2>
                    <div class="tiles">
                        @foreach ($section->items as $item)
                            @php $tag = $item->link_url ? 'a' : 'button'; @endphp
                            <{{ $tag }}
                                class="tile"
                                data-tile-label="{{ $item->label }}"
                                @if ($item->link_url)
                                    href="{{ $item->link_url }}"
                                @else
                                    type="button" data-toast="{{ $item->label }}"
                                @endif
                            >
                                <span class="icon" style="background:{{ $item->accent->hex() }}">
                                    @if ($item->iconUrl())
                                        <img src="{{ $item->iconUrl() }}" alt="">
                                    @else
                                        <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="4"/><path d="M9 12h6M12 9v6"/></svg>
                                    @endif
                                </span>
                                <span class="label">{{ $item->label }}</span>
                            </{{ $tag }}>
                        @endforeach
                    </div>
                </section>
            @empty
                <p class="empty">هنوز محتوایی برای این صفحه از پنل مدیریت ثبت نشده است.</p>
            @endforelse
        </main>
    </div>

    <div id="toast"><span></span></div>

    @include('partials.customer-app-pwa-register')
    @include('partials.customer-app-pwa-install-prompt')

    <script>
        (function () {
            var toastEl = document.getElementById('toast');
            var toastSpan = toastEl.querySelector('span');
            var toastTimer = null;

            function showToast(label) {
                toastSpan.textContent = label + ' — به‌زودی فعال می‌شود';
                toastSpan.classList.add('is-visible');
                clearTimeout(toastTimer);
                toastTimer = setTimeout(function () {
                    toastSpan.classList.remove('is-visible');
                }, 2200);
            }

            document.querySelectorAll('[data-toast]').forEach(function (el) {
                el.addEventListener('click', function (event) {
                    event.preventDefault();
                    showToast(el.dataset.toast);
                });
            });

            var searchInput = document.getElementById('tile-search');
            searchInput.addEventListener('input', function () {
                var query = searchInput.value.trim().toLowerCase();

                document.querySelectorAll('[data-section]').forEach(function (section) {
                    var visibleCount = 0;

                    section.querySelectorAll('.tile').forEach(function (tile) {
                        var matches = tile.dataset.tileLabel.toLowerCase().includes(query);
                        tile.style.display = matches ? '' : 'none';
                        if (matches) {
                            visibleCount += 1;
                        }
                    });

                    section.style.display = visibleCount > 0 ? '' : 'none';
                });
            });
        })();
    </script>
</body>
</html>
