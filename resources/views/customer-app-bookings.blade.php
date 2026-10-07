<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <title>سفارش‌های من — گنجنامه</title>

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
            gap: 1.5rem;
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

        .card {
            border: 1px solid var(--line);
            border-radius: 1rem;
            padding: 1rem;
        }

        .card h2 {
            font-size: .9rem;
            margin: 0 0 .9rem;
        }

        .search-form {
            display: flex;
            gap: .6rem;
        }

        .search-form label {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: .3rem;
            font-size: .75rem;
            color: var(--ink-soft);
        }

        .search-form input {
            font-family: inherit;
            font-size: .85rem;
            border: 1px solid var(--line);
            border-radius: .6rem;
            padding: .55rem .7rem;
            background: var(--paper);
            color: var(--ink);
        }

        .btn {
            appearance: none;
            border: none;
            font-family: inherit;
            font-size: .85rem;
            font-weight: 700;
            border-radius: .7rem;
            padding: .65rem 1rem;
            cursor: pointer;
            background: var(--orange);
            color: #fff;
        }

        .search-form .btn {
            flex: 0 0 auto;
            align-self: flex-end;
        }

        .room-list, .booking-list {
            display: flex;
            flex-direction: column;
            gap: .8rem;
        }

        .room-row, .booking-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .8rem;
            border: 1px solid var(--line);
            border-radius: .85rem;
            padding: .8rem .9rem;
        }

        .room-row .meta, .booking-row .meta {
            display: flex;
            flex-direction: column;
            gap: .2rem;
        }

        .room-row .name, .booking-row .name {
            font-size: .88rem;
            font-weight: 700;
        }

        .room-row .sub, .booking-row .sub {
            font-size: .76rem;
            color: var(--ink-faint);
        }

        .room-row .price {
            font-size: .82rem;
            font-weight: 700;
            color: var(--orange-dark);
            white-space: nowrap;
        }

        .badge {
            display: inline-block;
            font-size: .7rem;
            font-weight: 700;
            padding: .2rem .6rem;
            border-radius: 1rem;
            white-space: nowrap;
        }

        .badge.gray { background: #f1f1ef; color: var(--ink-soft); }
        .badge.warning { background: var(--orange-soft); color: var(--orange-dark); }
        .badge.info { background: #dbeafe; color: #1d4ed8; }
        .badge.success { background: var(--green-soft); color: var(--green); }
        .badge.danger { background: var(--red-soft); color: var(--red); }

        .empty {
            text-align: center;
            color: var(--ink-faint);
            font-size: .82rem;
            padding: 1.5rem 0;
        }

        .book-btn {
            appearance: none;
            border: none;
            font-family: inherit;
            font-size: .78rem;
            font-weight: 700;
            border-radius: .55rem;
            padding: .45rem .8rem;
            cursor: pointer;
            background: var(--orange);
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="shell">
        <header class="topbar">
            <a href="{{ route('customer-app') }}" class="back">→</a>
            <h1>سفارش‌های من</h1>
        </header>

        <main class="content">
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

            <section class="card">
                <h2>جستجوی اتاق/ویلای خالی</h2>
                <form method="GET" action="{{ route('customer-app.bookings') }}" class="search-form">
                    <label>
                        تاریخ ورود
                        <input type="date" name="check_in_date" value="{{ $checkInDate }}" required>
                    </label>
                    <label>
                        تاریخ خروج
                        <input type="date" name="check_out_date" value="{{ $checkOutDate }}" required>
                    </label>
                    <button type="submit" class="btn">جستجو</button>
                </form>
            </section>

            @if ($availableRooms !== null)
                <section class="card">
                    <h2>نتیجه جستجو</h2>
                    @if ($availableRooms->isEmpty())
                        <p class="empty">برای این بازه‌ی تاریخ، اتاق/ویلای خالی پیدا نشد.</p>
                    @else
                        <div class="room-list">
                            @foreach ($availableRooms as $row)
                                <div class="room-row">
                                    <div class="meta">
                                        <span class="name">{{ $row['room']->name }}</span>
                                        <span class="sub">{{ $row['room']->roomType->name }} · {{ $row['nights'] }} شب</span>
                                    </div>
                                    <form method="POST" action="{{ route('customer-app.bookings.store') }}" style="display:flex;align-items:center;gap:.6rem">
                                        @csrf
                                        <input type="hidden" name="room_id" value="{{ $row['room']->id }}">
                                        <input type="hidden" name="check_in_date" value="{{ $checkInDate }}">
                                        <input type="hidden" name="check_out_date" value="{{ $checkOutDate }}">
                                        <span class="price">{{ number_format($row['price_for_stay']) }} تومان</span>
                                        <button type="submit" class="book-btn">رزرو</button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endif

            <section class="card">
                <h2>تاریخچه رزروهای من</h2>
                @if ($bookings->isEmpty())
                    <p class="empty">هنوز رزروی ثبت نکرده‌اید.</p>
                @else
                    <div class="booking-list">
                        @foreach ($bookings as $booking)
                            <div class="booking-row">
                                <div class="meta">
                                    <span class="name">{{ $booking->room->name }}</span>
                                    <span class="sub">{{ $booking->check_in_date->toJalali('Y/m/d') }} تا {{ $booking->check_out_date->toJalali('Y/m/d') }} · {{ $booking->reference_code }}</span>
                                </div>
                                <span class="badge {{ $booking->status->getColor() }}">{{ $booking->status->getLabel() }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </main>
    </div>
</body>
</html>
