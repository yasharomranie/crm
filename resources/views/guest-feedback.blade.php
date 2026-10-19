<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>نظر شما درباره‌ی اقامت</title>
    <style>
        :root { color-scheme: light; --ink: #1c1917; --soft: #57534e; --paper: #fff7ed; --line: #f1e4d3; --accent: #c2410c; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100dvh; display: grid; place-items: center; padding: 16px; font-family: Vazirmatn, Tahoma, sans-serif; background: var(--paper); color: var(--ink); }
        main { width: 100%; max-width: 480px; background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 24px; }
        h1 { margin: 0 0 8px; font-size: 1.25rem; }
        p { color: var(--soft); line-height: 1.8; margin: 0 0 16px; }
        .scores { display: grid; grid-template-columns: repeat(auto-fit, minmax(40px, 1fr)); gap: 6px; margin-bottom: 16px; }
        .scores label { display: block; }
        .scores input { position: absolute; opacity: 0; }
        .scores span { display: block; text-align: center; padding: 10px 0; border: 1px solid var(--line); border-radius: 8px; cursor: pointer; }
        .scores input:checked + span { background: var(--accent); color: #fff; border-color: var(--accent); }
        .scores input:focus-visible + span { outline: 2px solid var(--ink); outline-offset: 2px; }
        .ends { display: flex; justify-content: space-between; font-size: .8rem; color: var(--soft); margin-bottom: 16px; }
        textarea { width: 100%; min-height: 96px; padding: 10px; border: 1px solid var(--line); border-radius: 8px; font: inherit; margin-bottom: 16px; }
        button { width: 100%; padding: 12px; border: 0; border-radius: 8px; background: var(--accent); color: #fff; font: inherit; font-weight: 700; cursor: pointer; }
        .error { color: #b91c1c; margin-bottom: 12px; }
    </style>
</head>
<body>
<main>
    @if ($answered)
        <h1>سپاسگزاریم{{ $feedback->guest ? '، '.$feedback->guest->name : '' }}</h1>
        <p>نظر شما ثبت شد و به ما کمک می‌کند اقامت مهمانان بعدی را بهتر کنیم.</p>
    @else
        <h1>اقامتتان چطور بود؟</h1>
        <p>با یک عدد از ۰ تا ۱۰ بگویید چقدر اقامت در مجموعه‌ی ما را به دوستانتان پیشنهاد می‌کنید.</p>

        <form method="post" action="{{ request()->fullUrl() }}">
            @csrf
            @error('nps_score')<div class="error">لطفاً یک امتیاز از ۰ تا ۱۰ انتخاب کنید.</div>@enderror
            <div class="scores" role="radiogroup" aria-label="امتیاز">
                @foreach (range(0, 10) as $score)
                    <label><input type="radio" name="nps_score" value="{{ $score }}" required><span>{{ \Illuminate\Support\Number::toPersian($score) }}</span></label>
                @endforeach
            </div>
            <div class="ends"><span>اصلاً</span><span>حتماً</span></div>
            <textarea name="comment" maxlength="1000" placeholder="اگر نکته‌ای دارید بنویسید (اختیاری)">{{ old('comment') }}</textarea>
            <button type="submit">ثبت نظر</button>
        </form>
    @endif
</main>
</body>
</html>
