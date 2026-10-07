<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <title>کیف پول و باشگاه — گنجنامه</title>

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
            gap: 1.2rem;
        }

        .wallet-card {
            border-radius: 1.1rem;
            padding: 1.3rem 1.1rem;
            background: linear-gradient(135deg, var(--orange), var(--orange-dark));
            color: #fff;
        }

        .wallet-card .label {
            font-size: .78rem;
            opacity: .9;
        }

        .wallet-card .amount {
            font-size: 1.7rem;
            font-weight: 800;
            margin-top: .3rem;
        }

        .tier-card {
            border: 1px solid var(--line);
            border-radius: 1rem;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            gap: .6rem;
        }

        .tier-badge {
            display: inline-flex;
            align-self: flex-start;
            font-size: .78rem;
            font-weight: 700;
            padding: .3rem .8rem;
            border-radius: 1rem;
        }

        .tier-badge.gray { background: #f1f1ef; color: var(--ink-soft); }
        .tier-badge.info { background: #dbeafe; color: #1d4ed8; }
        .tier-badge.warning { background: var(--orange-soft); color: var(--orange-dark); }
        .tier-badge.success { background: var(--green-soft); color: var(--green); }

        .tier-card p {
            font-size: .82rem;
            color: var(--ink-soft);
            margin: 0;
        }

        .tier-card .stat {
            font-size: .78rem;
            color: var(--ink-faint);
        }

        .card h2 {
            font-size: .9rem;
            margin: 0 0 .9rem;
        }

        .card {
            border: 1px solid var(--line);
            border-radius: 1rem;
            padding: 1rem;
        }

        .tx-list {
            display: flex;
            flex-direction: column;
            gap: .7rem;
        }

        .tx-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .8rem;
        }

        .tx-row .meta {
            display: flex;
            flex-direction: column;
            gap: .15rem;
        }

        .tx-row .reason {
            font-size: .85rem;
            font-weight: 700;
        }

        .tx-row .date {
            font-size: .74rem;
            color: var(--ink-faint);
        }

        .tx-row .amount {
            font-size: .85rem;
            font-weight: 700;
            white-space: nowrap;
        }

        .tx-row .amount.credit { color: var(--green); }
        .tx-row .amount.debit { color: var(--red); }

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
            <a href="{{ route('customer-app') }}" class="back">→</a>
            <h1>کیف پول و باشگاه مشتریان</h1>
        </header>

        <main class="content has-bottom-nav">
            <section class="wallet-card">
                <div class="label">موجودی کیف پول</div>
                <div class="amount">{{ number_format($guest->wallet_balance) }} تومان</div>
            </section>

            <section class="tier-card">
                <span class="tier-badge {{ $tier->getColor() }}">باشگاه مشتریان: {{ $tier->getLabel() }}</span>
                <p>
                    @switch($tier->value)
                        @case('vip')
                            به‌عنوان مشتری VIP، بیشترین میزان کش‌بک و اولویت رزرو را دارید.
                            @break
                        @case('gold')
                            به‌عنوان مشتری طلایی، از کش‌بک ویژه و پیشنهادهای اختصاصی بهره‌مند می‌شوید.
                            @break
                        @case('silver')
                            به‌عنوان مشتری نقره‌ای، در مسیر رسیدن به سطح طلایی هستید.
                            @break
                        @default
                            با تکمیل اولین اقامت خود، وارد سطح‌های بالاتر باشگاه مشتریان گنجنامه می‌شوید.
                    @endswitch
                </p>
                <span class="stat">تعداد اقامت تکمیل‌شده: {{ $completedStays }}</span>
            </section>

            <section class="card">
                <h2>تاریخچه تراکنش‌های کیف پول</h2>
                @if ($transactions->isEmpty())
                    <p class="empty">هنوز تراکنشی در کیف پول شما ثبت نشده است.</p>
                @else
                    <div class="tx-list">
                        @foreach ($transactions as $tx)
                            <div class="tx-row">
                                <div class="meta">
                                    <span class="reason">{{ $tx->reason }}</span>
                                    <span class="date">{{ $tx->created_at->toJalali('Y/m/d H:i') }}</span>
                                </div>
                                <span class="amount {{ $tx->type->value }}">
                                    {{ $tx->type->value === 'credit' ? '+' : '−' }}{{ number_format($tx->amount) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </main>
    </div>

    @include('partials.customer-app-bottom-nav')
</body>
</html>
