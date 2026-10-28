<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>پردازش تصاویر مرجع — راهنمای هوشمند</title>
    @fonts
    <style>
        body { margin: 0; font-family: 'Vazirmatn', ui-sans-serif, system-ui, sans-serif; background: #fff7ed; color: #1c1917; }
        main { max-width: 40rem; margin: 0 auto; padding: 1rem; display: flex; flex-direction: column; gap: 1rem; }
        h1 { font-size: 1.1rem; margin: 0; }
        p { margin: 0; line-height: 2; font-size: .9rem; color: #57534e; }
        button { font: inherit; border-radius: .7rem; padding: .7rem 1rem; border: 0; background: #f97316; color: #fff; font-weight: 700; cursor: pointer; }
        button:disabled { opacity: .5; cursor: wait; }
        button:focus-visible { outline: 3px solid #c2410c; outline-offset: 2px; }
        label { font-size: .85rem; display: flex; gap: .4rem; align-items: center; }
        ul { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: .4rem; }
        li { background: #fff; border: 1px solid #f1e4d3; border-radius: .6rem; padding: .6rem .8rem; font-size: .85rem; display: flex; justify-content: space-between; gap: 1rem; }
        li[data-state="done"] { color: #15803d; }
        li[data-state="failed"] { color: #b91c1c; }
        #summary { font-weight: 700; }
    </style>
</head>
<body>
<main>
    <h1>پردازش تصاویر مرجع</h1>
    <p>مدل شناسایی داخل همین مرورگر اجرا می‌شود و برای هر عکس یک «اثر انگشت» عددی می‌سازد؛ فقط همین اعداد روی سرور ذخیره می‌شوند. بار اول مدل (حدود ۳۰ مگابایت) دانلود می‌شود. اگر مدل عوض شده یا می‌خواهید همه را دوباره بسازید، گزینهٔ «همهٔ تصاویر» را بزنید.</p>
    <label for="all"><input type="checkbox" id="all"> همهٔ تصاویر (حتی پردازش‌شده‌ها)</label>
    <button type="button" id="run">شروع پردازش</button>
    <p id="summary" role="status" aria-live="polite"></p>
    <ul id="list"></ul>
    <p><a href="{{ url('/admin') }}">بازگشت به پنل</a></p>
</main>

<script type="module">
    import { loadEngine, embedBlob, csrfToken } from '/guide/guide-engine.js?v=1';

    const run = document.getElementById('run');
    const summary = document.getElementById('summary');
    const list = document.getElementById('list');

    const post = (url, body) => fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        body: JSON.stringify(body),
    });

    run.addEventListener('click', async () => {
        run.disabled = true;
        list.replaceChildren();
        summary.textContent = 'در حال بارگذاری مدل…';

        try {
            await loadEngine();
        } catch (error) {
            summary.textContent = 'مدل بارگذاری نشد. اتصال را بررسی کنید و دوباره تلاش کنید.';
            run.disabled = false;

            return;
        }

        const all = document.getElementById('all').checked ? '?all=1' : '';
        const { images } = await (await fetch('{{ route('guide.enroll.pending') }}' + all, { headers: { Accept: 'application/json' } })).json();
        let done = 0;
        let failed = 0;

        for (const image of images) {
            const row = document.createElement('li');
            row.dataset.state = 'working';
            row.textContent = `${image.object ?? '—'} · تصویر ${image.id}: در حال پردازش…`;
            list.append(row);

            try {
                const download = await fetch(image.url);
                if (!download.ok) {
                    throw new Error(`دانلود عکس: HTTP ${download.status}`);
                }
                const blob = await download.blob();
                const vector = await embedBlob(blob);
                const response = await post(`/guide/enroll/${image.id}`, { embedding: Array.from(vector) });
                if (!response.ok) {
                    throw new Error(`ذخیره روی سرور: HTTP ${response.status}`);
                }
                row.dataset.state = 'done';
                row.textContent = `${image.object ?? '—'} · تصویر ${image.id}: انجام شد`;
                done++;
            } catch (error) {
                row.dataset.state = 'failed';
                const reason = String(error?.message ?? error).slice(0, 200);
                row.textContent = `${image.object ?? '—'} · تصویر ${image.id}: ناموفق (${reason})`;
                await post(`/guide/enroll/${image.id}/failed`, { error: reason }).catch(() => {});
                failed++;
            }

            summary.textContent = `${done} انجام شد، ${failed} ناموفق، از ${images.length}`;
        }

        if (!images.length) {
            summary.textContent = 'تصویری برای پردازش نیست.';
        }

        run.disabled = false;
    });
</script>
</body>
</html>
