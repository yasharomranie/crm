<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <title>پروفایل کاربری — گنجنامه</title>

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
            font-size: .98rem;
            margin: 0;
        }

        .content {
            flex: 1;
            padding: 1rem 1rem 3rem;
            display: flex;
            flex-direction: column;
            gap: 1.2rem;
        }

        .profile-card {
            display: flex;
            align-items: center;
            gap: .9rem;
            border-radius: 1.1rem;
            padding: 1.2rem 1.1rem;
            background: linear-gradient(135deg, var(--orange), var(--orange-dark));
            color: #fff;
        }

        .profile-card .avatar {
            flex-shrink: 0;
            width: 3.2rem;
            height: 3.2rem;
            border-radius: 50%;
            background: rgb(255 255 255 / .22);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            font-weight: 800;
        }

        .profile-card .name {
            font-size: 1.05rem;
            font-weight: 700;
        }

        .profile-card .tier {
            font-size: .78rem;
            opacity: .9;
            margin-top: .2rem;
        }

        .card {
            border: 1px solid var(--line);
            border-radius: 1rem;
            padding: 1rem;
        }

        .card h2 {
            font-size: .9rem;
            margin: 0 0 .9rem;
        }

        .info-list {
            display: flex;
            flex-direction: column;
            gap: .8rem;
        }

        .info-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .8rem;
        }

        .info-row .label {
            font-size: .82rem;
            color: var(--ink-faint);
        }

        .info-row .value {
            font-size: .85rem;
            font-weight: 700;
        }

        .quick-links {
            display: flex;
            flex-direction: column;
            gap: .6rem;
        }

        .quick-links a {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: .85rem 1rem;
            border: 1px solid var(--line);
            border-radius: .85rem;
            text-decoration: none;
            color: var(--ink);
            font-size: .85rem;
            font-weight: 700;
        }

        .quick-links a span.arrow {
            color: var(--ink-faint);
            font-weight: 400;
        }

        .logout-form button {
            appearance: none;
            width: 100%;
            border: 1px solid var(--line);
            background: none;
            border-radius: .85rem;
            padding: .85rem 1rem;
            font-family: inherit;
            font-size: .85rem;
            font-weight: 700;
            color: #b91c1c;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="shell">
        <header class="topbar">
            <a href="{{ route('customer-app') }}" class="back">→</a>
            <h1>پروفایل کاربری</h1>
        </header>

        <main class="content has-bottom-nav">
            <section class="profile-card">
                <span class="avatar">{{ mb_substr($guest->name, 0, 1) }}</span>
                <div>
                    <div class="name">{{ $guest->name }}</div>
                    <div class="tier">باشگاه مشتریان: {{ $tier->getLabel() }}</div>
                </div>
            </section>

            <section class="card">
                <h2>اطلاعات حساب</h2>
                <div class="info-list">
                    @if ($guest->mobile)
                        <div class="info-row">
                            <span class="label">شماره موبایل</span>
                            <span class="value">{{ $guest->mobile }}</span>
                        </div>
                    @endif
                    @if ($guest->email)
                        <div class="info-row">
                            <span class="label">ایمیل</span>
                            <span class="value">{{ $guest->email }}</span>
                        </div>
                    @endif
                    <div class="info-row">
                        <span class="label">موجودی کیف پول</span>
                        <span class="value">{{ number_format($guest->wallet_balance) }} تومان</span>
                    </div>
                </div>
            </section>

            <section class="quick-links">
                <a href="{{ route('customer-app.bookings') }}">سفارش‌های من <span class="arrow">←</span></a>
                <a href="{{ route('customer-app.wallet') }}">کیف پول و باشگاه <span class="arrow">←</span></a>
                <a href="{{ route('customer-app.support') }}">پشتیبانی <span class="arrow">←</span></a>
            </section>

            <section class="quick-links" style="display:block;padding:1rem">
                @if (session('success'))
                    <p style="color:#15803d;font-size:.82rem;margin:0 0 .6rem">{{ session('success') }}</p>
                @endif
                <form method="POST" action="{{ route('customer-app.profile.consents') }}">
                    @csrf
                    <p style="margin:0 0 .5rem;font-weight:700;font-size:.88rem">دریافت پیشنهادها و اخبار</p>
                    <label style="display:flex;gap:.5rem;font-size:.82rem;margin-bottom:.4rem"><input type="checkbox" name="sms" value="1" {{ $smsConsent ? 'checked' : '' }}> پیامک</label>
                    <label style="display:flex;gap:.5rem;font-size:.82rem;margin-bottom:.6rem"><input type="checkbox" name="email" value="1" {{ $emailConsent ? 'checked' : '' }}> ایمیل</label>
                    <button type="submit" style="all:unset;cursor:pointer;color:#c2410c;font-weight:700;font-size:.82rem">ذخیره</button>
                </form>
            </section>

            <form class="logout-form" method="POST" action="{{ route('customer-app.auth.logout') }}">
                @csrf
                <button type="submit">خروج از حساب کاربری</button>
            </form>
        </main>
    </div>

    @include('partials.customer-app-bottom-nav')
</body>
</html>
