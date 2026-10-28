<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>راهنمای هوشمند — گنجنامه</title>

    @include('partials.customer-app-pwa-head')

    @fonts

    <style>
        :root {
            color-scheme: light;
            --orange: #f97316;
            --orange-dark: #c2410c;
            --orange-soft: #ffedd5;
            --green: #15803d;
            --red: #b91c1c;
            --ink: #1c1917;
            --ink-soft: #57534e;
            --paper: #fff7ed;
            --line: #f1e4d3;
        }

        * { box-sizing: border-box; }

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
            display: flex;
            flex-direction: column;
        }

        .topbar {
            padding: max(1rem, env(safe-area-inset-top)) 1rem .8rem;
            border-bottom: 1px solid var(--line);
            display: flex;
            align-items: center;
            gap: .7rem;
        }

        .topbar a { color: var(--ink-soft); text-decoration: none; }
        .topbar h1 { font-size: 1rem; margin: 0; }

        .preview-note {
            background: var(--orange-soft);
            color: var(--orange-dark);
            font-size: .8rem;
            padding: .5rem 1rem;
        }

        main { flex: 1; padding: 1rem; display: flex; flex-direction: column; gap: 1rem; }

        .viewport {
            position: relative;
            aspect-ratio: 1;
            background: #1c1917;
            border-radius: 1rem;
            overflow: hidden;
            display: grid;
            place-items: center;
        }

        .viewport video { width: 100%; height: 100%; object-fit: cover; }
        .viewport .frame {
            position: absolute;
            inset: 12%;
            border: 3px solid rgb(255 255 255 / .8);
            border-radius: 1rem;
            pointer-events: none;
        }
        .viewport[data-state="matched"] .frame { border-color: #4ade80; }
        .viewport .idle { color: #fff; text-align: center; padding: 1.5rem; font-size: .9rem; line-height: 1.9; }

        .status {
            text-align: center;
            font-weight: 700;
            font-size: 1rem;
            min-height: 3.2rem;
        }
        .status small { display: block; font-weight: 400; color: var(--ink-soft); font-size: .82rem; margin-top: .2rem; }
        .status[data-state="error"], .status[data-state="denied"], .status[data-state="network"] { color: var(--red); }
        .status[data-state="matched"] { color: var(--green); }

        button.primary {
            background: var(--orange);
            color: #fff;
            border: 0;
            border-radius: .8rem;
            padding: .9rem 1rem;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }
        button.primary:focus-visible { outline: 3px solid var(--orange-dark); outline-offset: 2px; }
        button.secondary {
            background: #fff;
            color: var(--orange-dark);
            border: 1px solid var(--orange);
            border-radius: .8rem;
            padding: .7rem 1rem;
            font: inherit;
            cursor: pointer;
        }

        .result { border: 1px solid var(--line); border-radius: 1rem; overflow: hidden; }
        .result img.cover { width: 100%; max-height: 14rem; object-fit: cover; display: block; }
        .result .body { padding: 1rem; display: flex; flex-direction: column; gap: .6rem; }
        .result h2 { margin: 0; font-size: 1.1rem; }
        .result .zone { color: var(--orange-dark); font-size: .8rem; }
        .result p { margin: 0; line-height: 2; font-size: .9rem; color: var(--ink-soft); white-space: pre-line; }
        .result video { width: 100%; border-radius: .7rem; background: #000; }
        [hidden] { display: none !important; }
        .debug { font-size: .75rem; color: var(--ink-soft); text-align: center; direction: ltr; }
    </style>
</head>
<body>
<div class="shell">
    <header class="topbar">
        <a href="{{ route('customer-app') }}" aria-label="بازگشت">→</a>
        <h1>راهنمای هوشمند گردشگری</h1>
    </header>

    @if ($staffPreview)
        <div class="preview-note">حالت آزمایش: راهنما برای گردشگران هنوز فعال نیست و فقط کارکنان وارد‌شده این صفحه را می‌بینند.</div>
    @endif

    <main>
        <div class="viewport" id="viewport" data-state="ready">
            <video id="camera" playsinline muted autoplay hidden></video>
            <div class="frame" id="frame" hidden></div>
            <div class="idle" id="idle">دوربین را روی یکی از اشیای موزه بگیرید تا اطلاعات و ویدیوی آن نمایش داده شود.</div>
        </div>

        <div class="status" id="status" data-state="ready" role="status" aria-live="polite">آماده اسکن</div>

        <button class="primary" id="start" type="button">شروع اسکن</button>
        <p class="debug" id="debug" hidden></p>

        <section class="result" id="result" hidden></section>
        <button class="secondary" id="again" type="button" hidden>اسکن دوباره</button>
    </main>
</div>

<script type="module">
    import { loadEngine, embed, rank, reportEvent } from '/guide/guide-engine.js?v=1';

    const $ = (id) => document.getElementById(id);
    const viewport = $('viewport');
    const video = $('camera');
    const statusBox = $('status');
    const startButton = $('start');
    const againButton = $('again');
    const resultBox = $('result');
    const debugBox = $('debug');
    const showDebug = @json($staffPreview);

    const MESSAGES = {
        ready: ['آماده اسکن', ''],
        loading: ['در حال آماده‌سازی…', 'بار اول چند ثانیه طول می‌کشد.'],
        scanning: ['در حال شناسایی…', 'دوربین را ثابت نگه دارید.'],
        matched: ['شیء شناسایی شد', ''],
        unmatched: ['شیء شناسایی نشد.', 'لطفاً زاویه دوربین را تغییر دهید.'],
        network: ['خطای اتصال', 'اتصال اینترنت را بررسی کنید و دوباره تلاش کنید.'],
        denied: ['عدم دسترسی به دوربین', 'اجازهٔ استفاده از دوربین را در تنظیمات مرورگر بدهید.'],
        error: ['خطا در شناسایی', 'دوباره تلاش کنید.'],
        empty: ['هنوز شیئی برای شناسایی آماده نیست.', ''],
    };

    let stream = null;
    let guide = null;
    let running = false;
    let timer = null;
    let scanStartedAt = 0;
    let unmatchedReported = false;
    let streak = { id: null, count: 0 };

    function setState(state) {
        const [title, hint] = MESSAGES[state];
        statusBox.dataset.state = state;
        viewport.dataset.state = state;
        statusBox.replaceChildren(document.createTextNode(title));
        if (hint) {
            const small = document.createElement('small');
            small.textContent = hint;
            statusBox.append(small);
        }
    }

    function stopCamera() {
        running = false;
        clearTimeout(timer);
        stream?.getTracks().forEach((track) => track.stop());
        stream = null;
        video.hidden = true;
        $('frame').hidden = true;
    }

    async function fetchGuide() {
        if (guide) {
            return guide;
        }
        const response = await fetch('/guide/data.json', { headers: { Accept: 'application/json' } });
        if (!response.ok) {
            throw new Error('network');
        }
        guide = await response.json();

        return guide;
    }

    async function begin() {
        resultBox.hidden = true;
        againButton.hidden = true;
        startButton.hidden = true;
        $('idle').hidden = true;
        streak = { id: null, count: 0 };
        unmatchedReported = false;
        setState('loading');

        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
        } catch (error) {
            setState('denied');
            startButton.hidden = false;

            return;
        }

        video.srcObject = stream;
        video.hidden = false;
        $('frame').hidden = false;
        await video.play().catch(() => {});

        try {
            await fetchGuide();
        } catch (error) {
            stopCamera();
            setState('network');
            startButton.hidden = false;

            return;
        }

        if (!guide.objects.length) {
            stopCamera();
            setState('empty');
            startButton.hidden = false;

            return;
        }

        try {
            await loadEngine();
        } catch (error) {
            stopCamera();
            setState('error');
            startButton.hidden = false;
            reportEvent({ type: 'scan_error' });

            return;
        }

        setState('scanning');
        running = true;
        scanStartedAt = performance.now();
        tick();
    }

    async function tick() {
        if (!running) {
            return;
        }

        const { similarity_threshold: threshold, min_margin: margin, frame_interval_ms: interval } = guide.settings;

        try {
            if (video.readyState >= 2 && video.videoWidth > 0) {
                const vector = await embed(video, video.videoWidth, video.videoHeight);
                const outcome = rank(vector, guide.objects, threshold, margin);

                if (showDebug && outcome.best) {
                    debugBox.hidden = false;
                    debugBox.textContent = `${outcome.best.object.slug} ${outcome.best.score.toFixed(3)} gap ${outcome.gap.toFixed(3)}`;
                }

                if (outcome.matched) {
                    streak = streak.id === outcome.best.object.id ? { id: streak.id, count: streak.count + 1 } : { id: outcome.best.object.id, count: 1 };

                    if (streak.count >= 2 && running) {
                        found(outcome.best.object, outcome.best.score);

                        return;
                    }
                } else {
                    streak = { id: null, count: 0 };
                }

                if (performance.now() - scanStartedAt > 15000) {
                    setState('unmatched');
                    if (!unmatchedReported) {
                        unmatchedReported = true;
                        reportEvent({ type: 'scan_unmatched', confidence: outcome.best ? Math.max(0, Math.min(1, outcome.best.score)) : null });
                    }
                }
            }
        } catch (error) {
            stopCamera();
            setState('error');
            startButton.hidden = false;
            reportEvent({ type: 'scan_error' });

            return;
        }

        if (running) {
            timer = setTimeout(tick, interval);
        }
    }

    function found(object, score) {
        stopCamera();
        setState('matched');
        againButton.hidden = false;
        reportEvent({
            type: 'scan_matched',
            object_id: object.id,
            confidence: Math.max(0, Math.min(1, score)),
            latency_ms: Math.round(performance.now() - scanStartedAt),
        });
        renderResult(object);
    }

    function renderResult(object) {
        resultBox.replaceChildren();

        if (object.cover_url) {
            const cover = document.createElement('img');
            cover.className = 'cover';
            cover.src = object.cover_url;
            cover.alt = object.title;
            resultBox.append(cover);
        }

        const body = document.createElement('div');
        body.className = 'body';

        const heading = document.createElement('h2');
        heading.textContent = object.title;
        body.append(heading);

        if (object.zone) {
            const zone = document.createElement('span');
            zone.className = 'zone';
            zone.textContent = object.zone;
            body.append(zone);
        }

        if (object.description) {
            const text = document.createElement('p');
            text.textContent = object.description;
            body.append(text);
        }

        for (const item of object.videos) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'secondary';
            button.textContent = `پخش ویدیو: ${item.title ?? 'معرفی'}`;
            button.addEventListener('click', () => {
                const player = document.createElement('video');
                player.controls = true;
                player.playsInline = true;
                player.src = item.url;
                if (item.poster_url) {
                    player.poster = item.poster_url;
                }
                button.replaceWith(player);
                player.play().catch(() => {});
                reportEvent({ type: 'video_play', object_id: object.id });
            });
            body.append(button);
        }

        resultBox.append(body);
        resultBox.hidden = false;
    }

    startButton.addEventListener('click', begin);
    againButton.addEventListener('click', begin);
    document.addEventListener('visibilitychange', () => {
        if (document.hidden && running) {
            stopCamera();
            setState('ready');
            startButton.hidden = false;
        }
    });
</script>

@include('partials.customer-app-pwa-register')
</body>
</html>
