<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <title>{{ $ticket->subject }} — گنجنامه</title>

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

        .topbar .meta {
            display: flex;
            flex-direction: column;
            gap: .15rem;
            min-width: 0;
        }

        .topbar h1 {
            font-size: .92rem;
            margin: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
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

        .card {
            border: 1px solid var(--line);
            border-radius: 1rem;
            padding: 1rem;
        }

        .card .description {
            font-size: .85rem;
            color: var(--ink-soft);
            white-space: pre-line;
            margin: .6rem 0 0;
        }

        .thread {
            display: flex;
            flex-direction: column;
            gap: .7rem;
        }

        .bubble {
            max-width: 82%;
            border-radius: 1rem;
            padding: .65rem .85rem;
        }

        .bubble .author {
            font-size: .7rem;
            font-weight: 700;
            margin-bottom: .2rem;
            display: block;
        }

        .bubble .body {
            font-size: .85rem;
            white-space: pre-line;
        }

        .bubble .date {
            font-size: .68rem;
            color: var(--ink-faint);
            margin-top: .3rem;
            display: block;
        }

        .bubble.from-guest {
            align-self: flex-end;
            background: var(--orange-soft);
            color: var(--orange-dark);
            border-bottom-left-radius: .3rem;
        }

        .bubble.from-guest .body {
            color: var(--ink);
        }

        .bubble.from-staff {
            align-self: flex-start;
            background: var(--paper);
            border: 1px solid var(--line);
            color: var(--ink-soft);
            border-bottom-right-radius: .3rem;
        }

        .empty {
            text-align: center;
            color: var(--ink-faint);
            font-size: .82rem;
            padding: 1rem 0;
        }

        label.field {
            display: flex;
            flex-direction: column;
            gap: .35rem;
            font-size: .8rem;
            color: var(--ink-soft);
        }

        label.field textarea {
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
            margin-top: .8rem;
        }
    </style>
</head>
<body>
    <div class="shell">
        <header class="topbar">
            <a href="{{ route('customer-app.support') }}" class="back">→</a>
            <div class="meta">
                <h1>{{ $ticket->subject }}</h1>
                <span class="badge {{ $ticket->status->getColor() }}">{{ $ticket->status->getLabel() }}</span>
            </div>
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

            @if ($ticket->description)
                <section class="card">
                    <p class="description">{{ $ticket->description }}</p>
                </section>
            @endif

            <section class="thread">
                @forelse ($ticket->replies as $reply)
                    <div class="bubble {{ $reply->isFromGuest() ? 'from-guest' : 'from-staff' }}">
                        <span class="author">{{ $reply->authorName() }}</span>
                        <span class="body">{{ $reply->body }}</span>
                        <span class="date">{{ $reply->created_at->toJalali('Y/m/d H:i') }}</span>
                    </div>
                @empty
                    <p class="empty">هنوز پاسخی ثبت نشده است.</p>
                @endforelse
            </section>

            @if ($ticket->canBeRated())
                <section class="card">
                    <form method="POST" action="{{ route('customer-app.support.rate', $ticket) }}">
                        @csrf
                        <label class="field">
                            {{ $ticket->csat_score ? 'امتیاز شما به پشتیبانی (می‌توانید تغییر دهید)' : 'از پاسخ پشتیبانی چقدر راضی بودید؟' }}
                            <select name="csat_score" required>
                                @foreach ([5 => '۵ - عالی', 4 => '۴ - خوب', 3 => '۳ - متوسط', 2 => '۲ - ضعیف', 1 => '۱ - خیلی ضعیف'] as $value => $label)
                                    <option value="{{ $value }}" {{ (int) old('csat_score', $ticket->csat_score) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="field">
                            نظر شما (اختیاری)
                            <textarea name="csat_comment" rows="2" maxlength="1000">{{ old('csat_comment', $ticket->csat_comment) }}</textarea>
                        </label>
                        <button type="submit" class="btn">ثبت امتیاز</button>
                    </form>
                </section>
            @endif

            <section class="card">
                <form method="POST" action="{{ route('customer-app.support.reply', $ticket) }}">
                    @csrf
                    <label class="field">
                        پاسخ شما
                        <textarea name="body" rows="3" required></textarea>
                    </label>
                    <button type="submit" class="btn">ارسال پاسخ</button>
                </form>
            </section>
        </main>
    </div>

    @include('partials.customer-app-bottom-nav')
</body>
</html>
