<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <title>تریپ‌پلنر — گنجنامه</title>

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
            --red: #b91c1c;
            --red-soft: #fee2e2;
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
            font-size: .98rem;
            margin: 0;
        }

        .content {
            flex: 1;
            padding: 1rem 1rem 3rem;
            display: flex;
            flex-direction: column;
            gap: 1.3rem;
        }

        .flash {
            background: var(--green-soft);
            color: var(--green);
            border-radius: .75rem;
            padding: .7rem .9rem;
            font-size: .82rem;
        }

        .errors {
            background: var(--red-soft);
            color: var(--red);
            border-radius: .75rem;
            padding: .7rem .9rem;
            font-size: .82rem;
        }

        .hero {
            border-radius: 1.2rem;
            padding: 1.4rem 1.2rem;
            background: linear-gradient(145deg, var(--orange), var(--orange-dark) 70%);
            color: #fff;
            position: relative;
            overflow: hidden;
        }

        .hero::after {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 85% 0%, rgb(255 255 255 / .22), transparent 55%);
        }

        .hero h2 {
            margin: 0 0 .3rem;
            font-size: 1.15rem;
            position: relative;
        }

        .hero p {
            margin: 0;
            font-size: .82rem;
            opacity: .92;
            position: relative;
            max-width: 22rem;
        }

        .card {
            border: 1px solid var(--line);
            border-radius: 1rem;
            padding: 1.1rem;
        }

        .card h3 {
            font-size: .92rem;
            margin: 0 0 1rem;
        }

        label.field {
            display: flex;
            flex-direction: column;
            gap: .35rem;
            font-size: .8rem;
            color: var(--ink-soft);
            margin-bottom: 1rem;
        }

        label.field input, label.field select {
            font-family: inherit;
            font-size: .88rem;
            border: 1px solid var(--line);
            border-radius: .7rem;
            padding: .65rem .8rem;
            background: var(--paper);
            color: var(--ink);
        }

        .chip-group {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
        }

        .chip-group input {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .chip-group label {
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            padding: .5rem .9rem;
            border: 1px solid var(--line);
            border-radius: 2rem;
            font-size: .78rem;
            color: var(--ink-soft);
            cursor: pointer;
            background: var(--paper);
            transition: all .15s ease;
        }

        .chip-group input:checked + label {
            background: var(--orange-soft);
            border-color: var(--orange);
            color: var(--orange-dark);
            font-weight: 700;
        }

        .pace-group {
            display: flex;
            gap: .5rem;
        }

        .pace-group input {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .pace-group label {
            flex: 1;
            text-align: center;
            padding: .6rem .5rem;
            border: 1px solid var(--line);
            border-radius: .75rem;
            font-size: .76rem;
            color: var(--ink-soft);
            cursor: pointer;
        }

        .pace-group input:checked + label {
            background: var(--orange);
            border-color: var(--orange);
            color: #fff;
            font-weight: 700;
        }

        .btn {
            appearance: none;
            border: none;
            font-family: inherit;
            font-size: .9rem;
            font-weight: 700;
            border-radius: .8rem;
            padding: .8rem 1rem;
            cursor: pointer;
            background: var(--orange);
            color: #fff;
            width: 100%;
        }

        .plan-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .8rem;
            border: 1px solid var(--line);
            border-radius: .9rem;
            padding: .85rem 1rem;
            text-decoration: none;
            color: inherit;
            margin-bottom: .7rem;
        }

        .plan-row:last-child {
            margin-bottom: 0;
        }

        .plan-row .meta {
            display: flex;
            flex-direction: column;
            gap: .2rem;
        }

        .plan-row .title {
            font-size: .85rem;
            font-weight: 700;
        }

        .plan-row .date {
            font-size: .74rem;
            color: var(--ink-faint);
        }

        .plan-row .arrow {
            color: var(--ink-faint);
        }

        .empty {
            text-align: center;
            color: var(--ink-faint);
            font-size: .82rem;
            padding: 1rem 0;
        }
    </style>
</head>
<body>
    <div class="shell">
        <header class="topbar">
            <a href="{{ route('customer-app') }}" class="back">→</a>
            <h1>تریپ‌پلنر هوشمند</h1>
        </header>

        <main class="content has-bottom-nav">
            @if (session('success'))
                <div class="flash">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="errors">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <section class="hero">
                <h2>چند روز در همدانی؟</h2>
                <p>بگو چقدر وقت داری و به چی علاقه داری، دستیار هوشمند یک برنامه واقع‌بینانه و دقیقه‌به‌دقیقه برایت می‌سازد - همراه با مسیریابی تا هر مقصد.</p>
            </section>

            <section class="card">
                <h3>ساخت برنامه جدید</h3>
                <form method="POST" action="{{ route('customer-app.trip-planner.store') }}">
                    @csrf
                    <label class="field">
                        چند روز در همدان هستید؟
                        <select name="days" required>
                            @foreach (range(1, 7) as $day)
                                <option value="{{ $day }}" {{ old('days') == $day ? 'selected' : '' }}>{{ $day }} روز</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="field">
                        تاریخ شروع (اختیاری)
                        <input type="date" name="starts_on" value="{{ old('starts_on') }}">
                    </label>

                    <label class="field">
                        علایق شما (اختیاری)
                        <div class="chip-group">
                            @foreach (['طبیعت‌گردی' => 'nature', 'تاریخی' => 'historical', 'مذهبی' => 'religious', 'ماجراجویی' => 'adventure', 'خرید' => 'shopping', 'خوراک' => 'food', 'خانوادگی' => 'family'] as $label => $value)
                                <input type="checkbox" name="interests[]" value="{{ $value }}" id="interest-{{ $value }}" {{ in_array($value, old('interests', [])) ? 'checked' : '' }}>
                                <label for="interest-{{ $value }}">{{ $label }}</label>
                            @endforeach
                        </div>
                    </label>

                    <label class="field">
                        سرعت گشت
                        <div class="pace-group">
                            <input type="radio" name="pace" value="relaxed" id="pace-relaxed" {{ old('pace', 'balanced') === 'relaxed' ? 'checked' : '' }}>
                            <label for="pace-relaxed">آرام</label>
                            <input type="radio" name="pace" value="balanced" id="pace-balanced" {{ old('pace', 'balanced') === 'balanced' ? 'checked' : '' }}>
                            <label for="pace-balanced">متعادل</label>
                            <input type="radio" name="pace" value="packed" id="pace-packed" {{ old('pace', 'balanced') === 'packed' ? 'checked' : '' }}>
                            <label for="pace-packed">فشرده</label>
                        </div>
                    </label>

                    <label class="field">
                        همسفرها
                        <select name="group_type">
                            <option value="">مشخص نیست</option>
                            @foreach (['solo' => 'تنها', 'couple' => 'زوج', 'family_kids' => 'خانواده با کودک', 'friends' => 'گروه دوستان', 'seniors' => 'همراه با سالمند'] as $value => $label)
                                <option value="{{ $value }}" {{ old('group_type') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="field">
                        بودجه‌ی بازدیدها
                        <select name="budget_level">
                            <option value="">مشخص نیست</option>
                            @foreach (['low' => 'کم (ارزان یا رایگان)', 'mid' => 'متوسط', 'high' => 'بالا'] as $value => $label)
                                <option value="{{ $value }}" {{ old('budget_level') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <div class="chip-group" style="margin-bottom: 1rem;">
                        <input type="checkbox" name="has_car" value="1" id="has-car" {{ old('has_car') ? 'checked' : '' }}>
                        <label for="has-car">خودروی شخصی دارم</label>
                    </div>

                    <button type="submit" class="btn" id="plan-submit">ساخت برنامه سفر</button>
                </form>
            </section>

            <section class="card">
                <h3>برنامه‌های قبلی من</h3>
                @if ($plans->isEmpty())
                    <p class="empty">هنوز برنامه‌ای نساخته‌اید.</p>
                @else
                    @foreach ($plans as $plan)
                        <a href="{{ route('customer-app.trip-planner.show', $plan) }}" class="plan-row">
                            <div class="meta">
                                <span class="title">برنامه {{ $plan->days }} روزه</span>
                                <span class="date">{{ $plan->created_at->toJalali('Y/m/d') }}</span>
                            </div>
                            <span class="arrow">←</span>
                        </a>
                    @endforeach
                @endif
            </section>
        </main>
    </div>

    @include('partials.customer-app-bottom-nav')
    <script>
        (function () {
            var form = document.getElementById('plan-submit') && document.getElementById('plan-submit').form;
            if (!form) { return; }
            form.addEventListener('submit', function () {
                var button = document.getElementById('plan-submit');
                button.disabled = true;
                button.textContent = 'در حال ساخت برنامه... (تا یک دقیقه ممکن است طول بکشد)';
            });
        })();
    </script>
</body>
</html>
