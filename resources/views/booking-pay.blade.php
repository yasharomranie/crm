<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>پرداخت رزرو {{ $booking->reference_code }}</title>
    <style>
        :root { color-scheme: light; --ink: #1c1917; --soft: #57534e; --paper: #fff7ed; --line: #f1e4d3; --accent: #c2410c; --ok: #15803d; --bad: #b91c1c; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100dvh; display: grid; place-items: center; padding: 16px; font-family: Vazirmatn, Tahoma, sans-serif; background: var(--paper); color: var(--ink); }
        main { width: 100%; max-width: 480px; background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 24px; }
        h1 { margin: 0 0 8px; font-size: 1.25rem; }
        p { color: var(--soft); line-height: 1.8; margin: 0 0 12px; }
        dl { display: grid; grid-template-columns: auto 1fr; gap: 6px 16px; margin: 0 0 20px; }
        dt { color: var(--soft); }
        dd { margin: 0; text-align: left; }
        .amount { font-size: 1.5rem; font-weight: 700; color: var(--accent); margin-bottom: 16px; }
        button { width: 100%; padding: 12px; border: 0; border-radius: 8px; background: var(--accent); color: #fff; font: inherit; font-weight: 700; cursor: pointer; }
        .error { color: var(--bad); margin-bottom: 12px; }
        .ok { color: var(--ok); font-weight: 700; }
    </style>
</head>
<body>
<main>
    <h1>پرداخت رزرو</h1>
    <dl>
        <dt>مهمان</dt><dd>{{ $booking->guest->name }}</dd>
        <dt>کد رزرو</dt><dd>{{ $booking->reference_code }}</dd>
        <dt>واحد</dt><dd>{{ $booking->room->name }}</dd>
        <dt>ورود تا خروج</dt><dd>{{ $booking->check_in_date->toJalali('Y/m/d') }} تا {{ $booking->check_out_date->toJalali('Y/m/d') }}</dd>
    </dl>

    @if ($link->status === \App\Models\BookingPaymentLink::STATUS_PAID)
        <p class="ok">پرداخت شما با موفقیت انجام شد. سپاسگزاریم.</p>
        <p>کد پیگیری: {{ $link->ref_id }}</p>
    @elseif ($link->isExpired())
        <p class="error">مهلت این لینک تمام شده است. لطفاً با پذیرش تماس بگیرید تا لینک تازه بفرستند.</p>
    @else
        <div class="amount">{{ \Illuminate\Support\Number::toPersian(number_format($link->amount)) }} تومان</div>

        @error('payment')<div class="error">{{ $message }}</div>@enderror

        <form method="post" action="{{ route('booking-pay.start', $link->token) }}">
            @csrf
            <button type="submit">پرداخت آنلاین</button>
        </form>
    @endif
</main>
</body>
</html>
