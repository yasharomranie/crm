<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <title>تیکت پشتیبانی — گنجنامه</title>

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

        label.field {
            display: flex;
            flex-direction: column;
            gap: .35rem;
            font-size: .8rem;
            color: var(--ink-soft);
            margin-bottom: .8rem;
        }

        label.field input, label.field textarea {
            font-family: inherit;
            font-size: .88rem;
            border: 1px solid var(--line);
            border-radius: .7rem;
            padding: .65rem .8rem;
            background: var(--paper);
            color: var(--ink);
            resize: vertical;
        }

        .btn {
            appearance: none;
            border: none;
            font-family: inherit;
            font-size: .88rem;
            font-weight: 700;
            border-radius: .75rem;
            padding: .7rem 1rem;
            cursor: pointer;
            background: var(--orange);
            color: #fff;
        }

        .ticket-row {
            border: 1px solid var(--line);
            border-radius: .85rem;
            padding: .8rem .9rem;
            margin-bottom: .7rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .8rem;
        }

        .ticket-row:last-child {
            margin-bottom: 0;
        }

        .ticket-row .meta {
            display: flex;
            flex-direction: column;
            gap: .2rem;
        }

        .ticket-row .subject {
            font-size: .85rem;
            font-weight: 700;
        }

        .ticket-row .date {
            font-size: .74rem;
            color: var(--ink-faint);
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
    </style>
</head>
<body>
    <div class="shell">
        <header class="topbar">
            <a href="{{ route('customer-app') }}" class="back">→</a>
            <h1>تیکت پشتیبانی</h1>
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
                <h2>ثبت تیکت جدید</h2>
                <form method="POST" action="{{ route('customer-app.support.store') }}">
                    @csrf
                    <label class="field">
                        موضوع
                        <input type="text" name="subject" required>
                    </label>
                    <label class="field">
                        توضیحات
                        <textarea name="description" rows="4"></textarea>
                    </label>
                    <button type="submit" class="btn">ثبت تیکت</button>
                </form>
            </section>

            <section class="card">
                <h2>تیکت‌های من</h2>
                @if ($tickets->isEmpty())
                    <p class="empty">هنوز تیکتی ثبت نکرده‌اید.</p>
                @else
                    @foreach ($tickets as $ticket)
                        <div class="ticket-row">
                            <div class="meta">
                                <span class="subject">{{ $ticket->subject }}</span>
                                <span class="date">{{ $ticket->created_at->toJalali('Y/m/d H:i') }}</span>
                            </div>
                            <span class="badge {{ $ticket->status->getColor() }}">{{ $ticket->status->getLabel() }}</span>
                        </div>
                    @endforeach
                @endif
            </section>
        </main>
    </div>
</body>
</html>
