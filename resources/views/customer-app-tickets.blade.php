<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <title>بلیط‌های من — گنجنامه</title>

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

        .product {
            margin-bottom: 1rem;
        }

        .product:last-child {
            margin-bottom: 0;
        }

        .product .name {
            font-size: .85rem;
            font-weight: 700;
            margin-bottom: .5rem;
        }

        .variant-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .6rem;
            border: 1px solid var(--line);
            border-radius: .8rem;
            padding: .6rem .8rem;
            margin-bottom: .5rem;
        }

        .variant-row .meta {
            display: flex;
            flex-direction: column;
            gap: .15rem;
        }

        .variant-row .v-name {
            font-size: .82rem;
            font-weight: 700;
        }

        .variant-row .price {
            font-size: .76rem;
            color: var(--orange-dark);
        }

        .buy-btn {
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

        .ticket-row {
            border: 1px solid var(--line);
            border-radius: 1rem;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: .6rem;
            margin-bottom: .9rem;
        }

        .ticket-row:last-child {
            margin-bottom: 0;
        }

        .ticket-row .v-name {
            font-size: .88rem;
            font-weight: 700;
        }

        .ticket-row .sub {
            font-size: .76rem;
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

        .qrcode {
            display: flex;
            justify-content: center;
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
            <a href="{{ route('customer-app') }}" class="back">→</a>
            <h1>بلیط و چک‌این</h1>
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
                <h2>خرید بلیط (پرداخت از کیف پول)</h2>
                @forelse ($products as $product)
                    @if ($product->ticketVariants->isNotEmpty())
                        <div class="product">
                            <div class="name">{{ $product->name }}</div>
                            @foreach ($product->ticketVariants as $variant)
                                @php $price = $variant->currentPrice(); @endphp
                                @if ($price)
                                    <div class="variant-row">
                                        <div class="meta">
                                            <span class="v-name">{{ $variant->name }}</span>
                                            <span class="price">{{ number_format($price->amount) }} تومان</span>
                                        </div>
                                        <form method="POST" action="{{ route('customer-app.tickets.store') }}">
                                            @csrf
                                            <input type="hidden" name="ticket_variant_id" value="{{ $variant->id }}">
                                            <input type="hidden" name="quantity" value="1">
                                            <button type="submit" class="buy-btn">خرید</button>
                                        </form>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                @empty
                    <p class="empty">در حال حاضر بلیطی برای فروش موجود نیست.</p>
                @endforelse
            </section>

            <section class="card">
                <h2>بلیط‌های من</h2>
                @if ($myTickets->isEmpty())
                    <p class="empty">هنوز بلیطی خریداری نکرده‌اید.</p>
                @else
                    @foreach ($myTickets as $ticket)
                        <div class="ticket-row">
                            <span class="v-name">{{ $ticket->ticketVariant->product->name }} — {{ $ticket->ticketVariant->name }}</span>
                            <span class="sub">{{ $ticket->ticket_number }}</span>
                            <span class="badge {{ $ticket->status->getColor() }}">{{ $ticket->status->getLabel() }}</span>
                            @if ($ticket->qrToken && ! $ticket->qrToken->isRevoked())
                                <div class="qrcode" id="qrcode-{{ $ticket->id }}" data-token="{{ $ticket->qrToken->token }}"></div>
                            @endif
                        </div>
                    @endforeach
                @endif
            </section>
        </main>
    </div>

    <script src="{{ asset('vendor/qrcodejs/qrcode.min.js') }}"></script>
    <script>
        if (window.QRCode) {
            document.querySelectorAll('[id^="qrcode-"]').forEach(function (el) {
                new QRCode(el, { text: el.dataset.token, width: 160, height: 160 });
            });
        }
    </script>
</body>
</html>
