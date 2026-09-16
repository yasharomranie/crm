<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <title>{{ config('app.name') }} — یک کلمه بنویس، یک سیستم را به حرکت درآور</title>
    <meta name="description" content="FarsiWP: دستیار هوش مصنوعی، فرم‌ساز و گردش‌کار، مدیریت اقامتگاه، پروژه، فروش و منابع انسانی، همه در یک سامانه.">

    @include('partials.pwa-head')

    @fonts

    @vite(['resources/css/app.css'])

    <style>
        :root {
            color-scheme: light;
            --ink: #1c1917;
            --ink-soft: #57534e;
            --ink-faint: #a8a29e;
            --paper: #fffbeb;
            --amber-100: #fef3c7;
            --amber-200: #fde68a;
            --amber-500: #f59e0b;
            --amber-600: #d97706;
            --amber-700: #b45309;
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
            background:
                radial-gradient(circle at 50% 0%, var(--amber-100) 0%, var(--paper) 45%, #fff 100%);
            color: var(--ink);
            overflow-x: hidden;
        }

        .stage {
            position: relative;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: max(1.5rem, env(safe-area-inset-top)) 1.25rem max(2rem, env(safe-area-inset-bottom));
        }

        /* ---------- Wordmark ---------- */

        .wordmark {
            /* A Latin brand mark ("FarsiWP") inside an RTL page: without
               this, the browser's bidi algorithm still lays out the flex
               row start-to-end in the page's own RTL direction, so the
               two spans render as "WP Farsi" instead of "FarsiWP". */
            direction: ltr;
            display: flex;
            align-items: baseline;
            gap: .3rem;
            font-weight: 800;
            font-size: 1.15rem;
            letter-spacing: -.01em;
            margin-block: .5rem 0;
        }

        .wordmark .accent {
            color: var(--amber-600);
        }

        /* ---------- Hero ---------- */

        .hero {
            flex: 1;
            width: 100%;
            max-width: 40rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1.75rem;
            text-align: center;
            transition: transform .6s cubic-bezier(.22, 1, .36, 1), opacity .5s ease;
        }

        .stage.is-open .hero {
            /* The fan's cards are all pushed OUTWARD from center by
               translateY(-radius) (see .card-slot below), so the fan's
               own center stays hollow by construction - the character
               only needs to shrink a little to sit comfortably inside
               that empty middle, not travel up toward the card ring
               (radius 300px away) chasing a "clear of every card" spot;
               that path was tried first and only ever traded one overlap
               for another (a top-center card, then getting clipped by the
               viewport edge) for no real benefit over just staying put. */
            transform: translateY(10px) scale(.7);
            /* Waits until the first card or two are already popping in
               (their own transition-delay starts at 0/70ms) before the
               character starts shrinking - without this, clicking the
               button showed one bare frame of a small, empty-handed
               character alone in the middle with no cards around it yet,
               which read as broken rather than as a coordinated reveal. */
            transition-delay: .18s;
        }

        @media (max-width: 640px) {
            .stage.is-open .hero {
                transform: translateY(-.5rem) scale(.72);
            }
        }

        /* ---------- Mascot (identical design to the AI assistant widget's
           creature - resources/views/livewire/ai-assistant-widget.blade.php -
           so the brand character is the same from the public site into the
           panel, just scaled up here as the page's hero visual) ---------- */

        .landing-creature {
            position: relative;
            display: block;
            width: 132px;
            height: 132px;
            filter: drop-shadow(0 18px 30px rgb(217 119 6 / .28));
        }

        .landing-creature .glow {
            position: absolute;
            inset: -22px;
            border-radius: 50%;
            background: radial-gradient(circle, rgb(245 158 11 / .35) 0%, transparent 70%);
            animation: creature-glow 3.2s ease-in-out infinite;
        }

        .landing-body {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            background: linear-gradient(155deg, var(--amber-200) 0%, var(--amber-500) 55%, var(--amber-700) 100%);
            animation: creature-bob 3s ease-in-out infinite;
        }

        .landing-ear {
            position: absolute;
            top: -14px;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: linear-gradient(155deg, var(--amber-200), var(--amber-500));
        }

        .landing-ear.is-start { inset-inline-start: 12px; rotate: -18deg; }
        .landing-ear.is-end { inset-inline-end: 12px; rotate: 18deg; }

        .landing-cheek {
            position: absolute;
            top: 62px;
            width: 20px;
            height: 12px;
            border-radius: 50%;
            background: rgb(255 255 255 / .35);
        }

        .landing-cheek.is-start { inset-inline-start: 14px; }
        .landing-cheek.is-end { inset-inline-end: 14px; }

        .landing-eyes {
            position: absolute;
            top: 50px;
            inset-inline: 0;
            display: flex;
            justify-content: center;
            gap: 24px;
        }

        .landing-eye {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: var(--ink);
            animation: creature-blink 4.5s ease-in-out infinite;
        }

        .landing-eye::after {
            content: '';
            position: absolute;
            top: 2px;
            inset-inline-start: 2px;
            width: 4px;
            height: 4px;
            border-radius: 50%;
            background: #fff;
        }

        .landing-mouth {
            position: absolute;
            top: 78px;
            inset-inline: 0;
            margin-inline: auto;
            width: 22px;
            height: 11px;
            border-radius: 0 0 22px 22px;
            background: var(--amber-700);
            opacity: .55;
        }

        .landing-sparkle {
            position: absolute;
            top: 8px;
            inset-inline-end: 4px;
            width: 20px;
            height: 20px;
            color: #fff;
            opacity: .85;
            animation: creature-sparkle 2.6s ease-in-out infinite;
        }

        @keyframes creature-bob {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-6px); }
        }

        @keyframes creature-glow {
            0%, 100% { opacity: .6; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.08); }
        }

        @keyframes creature-blink {
            0%, 92%, 100% { transform: scaleY(1); }
            96% { transform: scaleY(.15); }
        }

        @keyframes creature-sparkle {
            0%, 100% { opacity: .4; transform: scale(.85) rotate(0deg); }
            50% { opacity: 1; transform: scale(1.1) rotate(15deg); }
        }

        /* ---------- Tagline ---------- */

        .tagline h1 {
            margin: 0;
            font-size: clamp(1.5rem, 4vw + .5rem, 2.35rem);
            font-weight: 800;
            line-height: 1.5;
            text-wrap: balance;
        }

        .tagline h1 .word {
            display: inline-block;
            opacity: 0;
            transform: translateY(14px);
            animation: word-in .6s cubic-bezier(.22, 1, .36, 1) forwards;
        }

        .tagline .brand-expansion {
            margin: .6rem 0 0;
            font-size: .82rem;
            color: var(--ink-faint);
            opacity: 0;
            animation: fade-up .6s ease forwards;
            animation-delay: 1.05s;
        }

        @keyframes word-in {
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fade-up {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .stage.is-open .tagline {
            opacity: 0;
            pointer-events: none;
            transition: opacity .35s ease;
        }

        /* ---------- CTA buttons ---------- */

        .cta-row {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: .75rem;
            opacity: 0;
            animation: fade-up .6s cubic-bezier(.22, 1, .36, 1) forwards;
            animation-delay: 1.25s;
        }

        .stage.is-open .cta-row {
            /* !important is load-bearing here, not stylistic: .cta-row
               carries its own entrance "animation: fade-up ... forwards",
               and a still-filling animation's held value overrides a
               plain declaration of the same property at any specificity -
               this is the one legitimate way to win that override. */
            opacity: 0 !important;
            pointer-events: none;
            transition: opacity .3s ease;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .4rem;
            min-height: 48px;
            padding: 0 1.6rem;
            border-radius: 999px;
            font-size: .95rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            border: 1.5px solid transparent;
            transition: transform .18s ease, box-shadow .18s ease, background-color .18s ease;
        }

        .btn:active {
            transform: scale(.97);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--amber-500), var(--amber-600));
            color: #fff;
            box-shadow: 0 10px 24px -8px rgb(217 119 6 / .55);
        }

        .btn-primary:hover {
            box-shadow: 0 14px 28px -8px rgb(217 119 6 / .65);
        }

        .btn-outline {
            background: #fff;
            color: var(--amber-700);
            border-color: var(--amber-200);
        }

        .btn-outline:hover {
            background: var(--amber-100);
            border-color: var(--amber-500);
        }

        .btn:focus-visible {
            outline: 2px solid var(--amber-600);
            outline-offset: 2px;
        }

        /* ---------- Features overlay (radial fan) ---------- */

        .features-overlay {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
        }

        .stage.is-open .features-overlay {
            pointer-events: auto;
        }

        .close-btn {
            position: absolute;
            top: max(1.25rem, env(safe-area-inset-top));
            inset-inline-end: 1.25rem;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: 1.5px solid var(--amber-200);
            background: #fff;
            color: var(--ink-soft);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            transform: scale(.8);
            transition: opacity .3s ease .3s, transform .3s ease .3s, background-color .18s ease;
        }

        .stage.is-open .close-btn {
            opacity: 1;
            transform: scale(1);
        }

        .close-btn:hover {
            background: var(--amber-100);
        }

        .features-fan {
            position: relative;
            width: min(820px, 94vw);
            height: min(820px, 88dvh);
        }

        .card-slot {
            /* 170deg spread over 5 gaps (6 cards) at a 300px radius gives
               ~178px of arc length between adjacent card centers - just
               enough clearance for a 148px-wide card plus a gutter, so
               neighbors don't visually overlap (a tighter arc/shorter
               radius was tried first and did overlap - the arc length has
               to exceed the card's own width, not just look roomy). */
            --angle: calc(-85deg + (170deg / 5) * var(--i));
            --radius: 300px;
            position: absolute;
            top: 50%;
            /* Physical `left`, not the logical `inset-inline-start`: this
               page is RTL, so inset-inline-start resolves to the RIGHT
               edge here - combined with the plain (physical) `margin-left`
               below meant for centering, that mismatch pulled every card
               off its intended angle/radius position by a fixed amount,
               which is exactly the "left/right spacing isn't even"
               reported. The angle math itself is a physical rotate()
               around this point, so the anchor needs to be physical too. */
            left: 50%;
            width: 148px;
            margin: -72px 0 0 -74px;
            transform: rotate(var(--angle)) translateY(calc(-1 * var(--radius))) rotate(calc(-1 * var(--angle)));
        }

        .feature-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: .5rem;
            padding: 1.1rem .9rem;
            border-radius: 1.25rem;
            background: #fff;
            border: 1px solid var(--amber-100);
            box-shadow: 0 12px 28px -12px rgb(28 25 23 / .18);
            text-align: center;
            opacity: 0;
            transform: scale(.35);
            transition: opacity .5s cubic-bezier(.34, 1.56, .64, 1), transform .5s cubic-bezier(.34, 1.56, .64, 1);
            transition-delay: calc(var(--i) * 70ms);
        }

        .stage.is-open .feature-card {
            opacity: 1;
            transform: scale(1);
        }

        .feature-card .icon-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            border-radius: .9rem;
            background: linear-gradient(155deg, var(--amber-100), var(--amber-200));
            color: var(--amber-700);
        }

        .feature-card .icon-wrap svg {
            width: 24px;
            height: 24px;
        }

        .feature-card h3 {
            margin: 0;
            font-size: .92rem;
            font-weight: 700;
        }

        .feature-card p {
            margin: 0;
            font-size: .76rem;
            line-height: 1.6;
            color: var(--ink-soft);
        }

        /* Mobile: a fanned semicircle has nowhere to go on a narrow screen,
           so the same cards become a plain scrollable vertical list instead
           - same reveal choreography (stagger + overshoot), different
           layout, per this app's own "no horizontal scroll" rule. */
        @media (max-width: 640px) {
            .features-fan {
                width: 100%;
                height: auto;
                max-height: 74dvh;
                overflow-y: auto;
                display: flex;
                flex-direction: column;
                gap: .75rem;
                padding: 4.5rem 1rem 1.5rem;
            }

            .card-slot {
                position: static;
                width: 100%;
                margin: 0;
                transform: none;
            }

            .feature-card {
                flex-direction: row;
                text-align: start;
                padding: .9rem 1rem;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: .01ms !important;
            }

            .landing-body, .glow, .landing-eye, .landing-sparkle {
                animation: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="stage" id="stage">
        <div class="wordmark" aria-hidden="true">
            <span>Farsi</span><span class="accent">WP</span>
        </div>

        <main class="hero">
            <div class="landing-creature" role="img" aria-label="کاراکتر دستیار هوش مصنوعی FarsiWP">
                <span class="glow"></span>
                <span class="landing-ear is-start"></span>
                <span class="landing-ear is-end"></span>
                <span class="landing-body"></span>
                <span class="landing-cheek is-start"></span>
                <span class="landing-cheek is-end"></span>
                <span class="landing-eyes">
                    <span class="landing-eye"></span>
                    <span class="landing-eye"></span>
                </span>
                <span class="landing-mouth"></span>
                <svg viewBox="0 0 24 24" fill="currentColor" class="landing-sparkle">
                    <path d="M12 2l1.6 5.4L19 9l-5.4 1.6L12 16l-1.6-5.4L5 9l5.4-1.6L12 2z" />
                </svg>
            </div>

            <div class="tagline">
                <h1>
                    @php $taglineWords = explode(' ', 'یک کلمه بنویس، یک سیستم را به حرکت درآور!'); @endphp
                    @foreach ($taglineWords as $i => $word)
                        <span class="word" style="animation-delay: {{ .35 + $i * .07 }}s">{{ $word }}</span>{{ $loop->last ? '' : ' ' }}
                    @endforeach
                </h1>
                <p class="brand-expansion">FarsiWP — Fully Automated Relationship &amp; System Intelligence, Word Pipeline</p>
            </div>

            <div class="cta-row">
                <a href="{{ url('/admin/login') }}" class="btn btn-primary">
                    ورود
                </a>
                <button type="button" class="btn btn-outline" id="revealBtn" aria-expanded="false" aria-controls="featuresOverlay">
                    چه کاری می‌تونی انجام بدی؟
                </button>
            </div>
        </main>

        <div class="features-overlay" id="featuresOverlay" aria-hidden="true">
            <button type="button" class="close-btn" id="closeBtn" aria-label="بستن نمای امکانات">
                <x-heroicon-o-x-mark class="h-5 w-5" />
            </button>

            <div class="features-fan">
                @php
                    $features = [
                        ['icon' => 'sparkles', 'title' => 'دستیار هوشمند مدیریتی', 'desc' => 'سؤال بپرس، از هر بخش سیستم گزارش و تحلیل آنی بگیر'],
                        ['icon' => 'clipboard-document-check', 'title' => 'فرم‌ساز و گردش‌کار', 'desc' => 'هر فرم اداری را با گردش تأیید چندمرحله‌ای بساز'],
                        ['icon' => 'building-office-2', 'title' => 'مدیریت اقامتگاه', 'desc' => 'اتاق‌ها، رزروها، درآمد و کانال‌های فروش'],
                        ['icon' => 'rectangle-stack', 'title' => 'پروژه و وظایف', 'desc' => 'پیگیری پروژه، چک‌لیست، تخصیص کار به تیم'],
                        ['icon' => 'chart-bar', 'title' => 'فروش و CRM', 'desc' => 'پایپ‌لاین معاملات، قراردادها، مشتریان'],
                        ['icon' => 'lifebuoy', 'title' => 'پشتیبانی و منابع انسانی', 'desc' => 'تیکت مهمان، حضور و غیاب، اسناد سازمانی'],
                    ];
                @endphp

                @foreach ($features as $i => $feature)
                    <div class="card-slot" style="--i: {{ $i }}">
                        <div class="feature-card">
                            <span class="icon-wrap">
                                <x-dynamic-component :component="'heroicon-o-'.$feature['icon']" />
                            </span>
                            <h3>{{ $feature['title'] }}</h3>
                            <p>{{ $feature['desc'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <script>
        (function () {
            var stage = document.getElementById('stage');
            var revealBtn = document.getElementById('revealBtn');
            var closeBtn = document.getElementById('closeBtn');
            var overlay = document.getElementById('featuresOverlay');

            function open() {
                stage.classList.add('is-open');
                revealBtn.setAttribute('aria-expanded', 'true');
                overlay.setAttribute('aria-hidden', 'false');
                closeBtn.focus({ preventScroll: true });
            }

            function close() {
                stage.classList.remove('is-open');
                revealBtn.setAttribute('aria-expanded', 'false');
                overlay.setAttribute('aria-hidden', 'true');
                revealBtn.focus({ preventScroll: true });
            }

            revealBtn.addEventListener('click', open);
            closeBtn.addEventListener('click', close);

            overlay.addEventListener('click', function (event) {
                if (event.target === overlay) {
                    close();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && stage.classList.contains('is-open')) {
                    close();
                }
            });
        })();
    </script>

    @include('partials.pwa-register')
</body>
</html>
