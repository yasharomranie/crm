<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>پیش‌ثبت‌نام رزرو {{ $booking->reference_code }}</title>
    <style>
        :root { color-scheme: light; --ink: #1c1917; --soft: #57534e; --paper: #fff7ed; --line: #f1e4d3; --accent: #c2410c; --ok: #15803d; --bad: #b91c1c; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100dvh; padding: 16px; font-family: Vazirmatn, Tahoma, sans-serif; background: var(--paper); color: var(--ink); }
        main { width: 100%; max-width: 520px; margin: 0 auto; background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 24px; }
        h1 { margin: 0 0 8px; font-size: 1.25rem; }
        h2 { margin: 20px 0 8px; font-size: 1rem; }
        p { color: var(--soft); line-height: 1.8; margin: 0 0 12px; }
        dl { display: grid; grid-template-columns: auto 1fr; gap: 6px 16px; margin: 0 0 16px; }
        dt { color: var(--soft); }
        dd { margin: 0; text-align: left; }
        label { display: block; margin-bottom: 4px; font-weight: 600; }
        input, textarea { width: 100%; padding: 10px; border: 1px solid var(--line); border-radius: 8px; font: inherit; margin-bottom: 12px; }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        button { width: 100%; padding: 12px; border: 0; border-radius: 8px; background: var(--accent); color: #fff; font: inherit; font-weight: 700; cursor: pointer; }
        .error { color: var(--bad); margin: -8px 0 12px; font-size: .9rem; }
        .ok { color: var(--ok); font-weight: 700; }
        @media (max-width: 420px) { .row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<main>
    <h1>پیش‌ثبت‌نام آنلاین</h1>
    <dl>
        <dt>مهمان</dt><dd>{{ $booking->guest->name }}</dd>
        <dt>کد رزرو</dt><dd>{{ $booking->reference_code }}</dd>
        <dt>واحد</dt><dd>{{ $booking->room->name }}</dd>
        <dt>ورود تا خروج</dt><dd>{{ $booking->check_in_date->toJalali('Y/m/d') }} تا {{ $booking->check_out_date->toJalali('Y/m/d') }}</dd>
    </dl>

    @if (! $open)
        <p>پیش‌ثبت‌نام این رزرو بسته است{{ $precheckin->isCompleted() ? '؛ اطلاعات شما قبلاً ثبت شده است' : '' }}. برای هر تغییر با پذیرش تماس بگیرید.</p>
    @else
        @if (session('saved'))
            <p class="ok">اطلاعات شما ثبت شد. تا پیش از ورود می‌توانید دوباره آن را ویرایش کنید.</p>
        @else
            <p>با تکمیل این فرم، پذیرش هنگام ورود سریع‌تر کارتان را انجام می‌دهد.</p>
        @endif

        <form method="post" action="{{ route('booking-precheckin.store', $precheckin->token) }}">
            @csrf
            <label for="national_code">کد ملی شما</label>
            <input id="national_code" name="national_code" inputmode="numeric" maxlength="10" required value="{{ old('national_code', $precheckin->national_code ?? $booking->guest->national_code) }}">
            @error('national_code')<div class="error">کد ملی باید ۱۰ رقم باشد.</div>@enderror

            <label for="arrival_time">ساعت تقریبی رسیدن (اختیاری)</label>
            <input id="arrival_time" name="arrival_time" type="time" value="{{ old('arrival_time', $precheckin->arrival_time) }}">
            @error('arrival_time')<div class="error">ساعت معتبر نیست.</div>@enderror

            <h2>همراهان (اختیاری)</h2>
            @php
                $saved = old('companions', $precheckin->companions ?? []);
                $slots = max(count($saved) + 1, min($booking->adults_count + $booking->children_count - 1, \App\Models\BookingPrecheckin::MAX_COMPANIONS), 1);
            @endphp
            @foreach (range(0, $slots - 1) as $i)
                <div class="row">
                    <div>
                        <label for="companion-name-{{ $i }}">نام همراه {{ \Illuminate\Support\Number::toPersian($i + 1) }}</label>
                        <input id="companion-name-{{ $i }}" name="companions[{{ $i }}][name]" value="{{ $saved[$i]['name'] ?? '' }}">
                    </div>
                    <div>
                        <label for="companion-code-{{ $i }}">کد ملی</label>
                        <input id="companion-code-{{ $i }}" name="companions[{{ $i }}][national_code]" inputmode="numeric" maxlength="10" value="{{ $saved[$i]['national_code'] ?? '' }}">
                    </div>
                </div>
            @endforeach
            @if ($errors->has('companions.*') || $errors->has('companions'))
                <div class="error">برای هر همراه نام را بنویسید و کد ملی (اگر نوشتید) ۱۰ رقم باشد.</div>
            @endif

            <label for="requests">درخواست ویژه (اختیاری)</label>
            <textarea id="requests" name="requests" rows="3" maxlength="1000" placeholder="مثلاً تخت اضافه، ورود زودهنگام، رژیم غذایی...">{{ old('requests', $precheckin->requests) }}</textarea>

            <button type="submit">{{ $precheckin->isCompleted() ? 'به‌روزرسانی اطلاعات' : 'ثبت اطلاعات' }}</button>
        </form>
    @endif
</main>
</body>
</html>
