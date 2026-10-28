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
            color-scheme: dark;
            --orange: #f97316;
            --orange-dark: #c2410c;
            --green: #4ade80;
            --red: #fca5a5;
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

        .shell { max-width: 32rem; margin: 0 auto; background: #fff; min-height: 100dvh; }

        .stage {
            position: relative;
            height: 72dvh;
            min-height: 22rem;
            background: #0c0a09;
            color: #fff;
            overflow: hidden;
        }

        .stage video { position: absolute; inset: 0; width: 100%; height: 100%; }
        #camera { object-fit: cover; }
        #story { object-fit: contain; background: #000; }

        .stage .shade {
            position: absolute;
            inset: 0;
            background: linear-gradient(to bottom, rgb(0 0 0 / .55), transparent 28%, transparent 60%, rgb(0 0 0 / .7));
            pointer-events: none;
        }

        .top {
            position: absolute;
            inset-inline: 0;
            top: 0;
            z-index: 3;
            padding: max(.8rem, env(safe-area-inset-top)) 1rem .6rem;
            display: flex;
            align-items: center;
            gap: .7rem;
        }
        .top a { color: #fff; text-decoration: none; font-size: 1.2rem; }
        .top h1 { font-size: .95rem; margin: 0; }
        .preview-note { font-size: .7rem; background: var(--orange); color: #fff; border-radius: 99px; padding: .15rem .6rem; }

        .reticle {
            position: absolute;
            inset: 20% 14%;
            z-index: 2;
            border-radius: 1.2rem;
            pointer-events: none;
            --c: rgb(255 255 255 / .9);
            background:
                linear-gradient(var(--c), var(--c)) top left / 2rem 3px no-repeat,
                linear-gradient(var(--c), var(--c)) top left / 3px 2rem no-repeat,
                linear-gradient(var(--c), var(--c)) top right / 2rem 3px no-repeat,
                linear-gradient(var(--c), var(--c)) top right / 3px 2rem no-repeat,
                linear-gradient(var(--c), var(--c)) bottom left / 2rem 3px no-repeat,
                linear-gradient(var(--c), var(--c)) bottom left / 3px 2rem no-repeat,
                linear-gradient(var(--c), var(--c)) bottom right / 2rem 3px no-repeat,
                linear-gradient(var(--c), var(--c)) bottom right / 3px 2rem no-repeat;
        }
        .reticle .sweep {
            position: absolute;
            inset-inline: 6%;
            height: 2px;
            background: linear-gradient(to right, transparent, var(--orange), transparent);
            animation: sweep 2.2s ease-in-out infinite alternate;
        }
        .stage[data-state="matched"] .reticle,
        .stage[data-state="story"] .reticle { --c: var(--green); }
        .stage[data-state="matched"] .sweep,
        .stage[data-state="story"] .sweep { display: none; }
        @keyframes sweep { from { top: 6%; } to { top: 94%; } }

        .intro, .loading {
            position: absolute;
            inset: 0;
            z-index: 4;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            padding: 2rem 1.5rem;
            text-align: center;
            line-height: 2;
            font-size: .92rem;
        }
        .intro { background: rgb(12 10 9 / .72); }
        .loading { background: rgb(12 10 9 / .78); font-weight: 700; }
        .loading small { display: block; font-weight: 400; opacity: .8; }

        .spinner {
            width: 2.6rem;
            height: 2.6rem;
            border: 4px solid rgb(255 255 255 / .25);
            border-top-color: var(--orange);
            border-radius: 50%;
            animation: spin .9s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .status {
            position: absolute;
            inset-inline: 0;
            bottom: 0;
            z-index: 3;
            padding: .8rem 1rem max(1rem, env(safe-area-inset-bottom));
            text-align: center;
            font-weight: 700;
            font-size: 1rem;
        }
        .status small { display: block; font-weight: 400; opacity: .85; font-size: .8rem; margin-top: .15rem; }
        .stage[data-state="matched"] .status, .stage[data-state="story"] .status { color: var(--green); }
        .stage[data-state="error"] .status, .stage[data-state="denied"] .status, .stage[data-state="network"] .status, .stage[data-state="videoError"] .status { color: var(--red); }

        .controls {
            position: absolute;
            inset-inline: 0;
            bottom: 0;
            z-index: 5;
            padding: .8rem 1rem max(1rem, env(safe-area-inset-bottom));
            display: flex;
            gap: .6rem;
            justify-content: center;
            background: linear-gradient(to top, rgb(0 0 0 / .7), transparent);
        }

        button {
            font: inherit;
            border: 0;
            border-radius: .8rem;
            padding: .8rem 1.2rem;
            cursor: pointer;
            font-weight: 700;
        }
        button:focus-visible { outline: 3px solid #fff; outline-offset: 2px; }
        button.primary { background: var(--orange); color: #fff; }
        button.glass { background: rgb(255 255 255 / .18); color: #fff; backdrop-filter: blur(6px); }
        button.pulse { animation: pulse 1.4s ease-in-out infinite; }
        @keyframes pulse { 50% { box-shadow: 0 0 0 .5rem rgb(249 115 22 / .35); } }

        .sheet { padding: 1.1rem 1rem 3rem; display: flex; flex-direction: column; gap: .7rem; }
        .sheet h2 { margin: 0; font-size: 1.15rem; }
        .sheet .zone { color: var(--orange-dark); font-size: .8rem; }
        .sheet p { margin: 0; line-height: 2; font-size: .92rem; color: var(--ink-soft); white-space: pre-line; }
        .sheet .others { display: flex; flex-direction: column; gap: .5rem; }
        .sheet button.secondary { background: #fff; color: var(--orange-dark); border: 1px solid var(--orange); text-align: start; }
        .sheet .hint { color: var(--ink-soft); font-size: .85rem; }
        .debug { font-size: .72rem; color: var(--ink-soft); direction: ltr; text-align: center; margin: 0; }
        [hidden] { display: none !important; }

        @media (prefers-reduced-motion: reduce) {
            .reticle .sweep, .spinner, button.pulse { animation: none; }
        }
    </style>
</head>
<body>
<div class="shell">
    <div class="stage" id="stage" data-state="ready">
        <video id="camera" playsinline muted autoplay hidden></video>
        <video id="story" playsinline preload="auto" hidden></video>
        <div class="shade"></div>

        <header class="top">
            <a href="{{ route('customer-app') }}" aria-label="بازگشت">→</a>
            <h1>راهنمای هوشمند گنجنامه</h1>
            @if ($staffPreview)
                <span class="preview-note">حالت آزمایش</span>
            @endif
        </header>

        <div class="reticle" id="reticle" hidden><i class="sweep"></i></div>

        <div class="intro" id="intro">
            <div>دوربین را روی یکی از اشیای موزه بگیرید؛ بعد از شناسایی، داستان آن با ویدیو و صدا شروع می‌شود.</div>
            <button class="primary" id="start" type="button">شروع اسکن</button>
        </div>

        <div class="loading" id="loading" hidden role="status" aria-live="polite">
            <div class="spinner" aria-hidden="true"></div>
            <div id="loadingText">در حال لود کردن داستان…<small id="loadingTitle"></small></div>
        </div>

        <div class="status" id="status" role="status" aria-live="polite" data-state="ready"></div>

        <div class="controls" id="controls" hidden>
            <button class="glass" id="sound" type="button">صدا</button>
            <button class="glass" id="replay" type="button" hidden>پخش دوباره</button>
            <button class="primary" id="again" type="button">اسکن شیء بعدی</button>
        </div>
    </div>

    <section class="sheet" id="sheet" hidden></section>
    <p class="debug" id="debug" hidden></p>
</div>

<script type="module">
    import { loadEngine, embed, rank, reportEvent } from '/guide/guide-engine.js?v=1';

    const $ = (id) => document.getElementById(id);
    const stage = $('stage');
    const camera = $('camera');
    const story = $('story');
    const statusBox = $('status');
    const showDebug = @json($staffPreview);

    const MESSAGES = {
        ready: ['آماده اسکن', ''],
        loading: ['در حال آماده‌سازی…', 'بار اول چند ثانیه طول می‌کشد.'],
        scanning: ['در حال شناسایی…', 'دوربین را ثابت نگه دارید.'],
        matched: ['شیء شناسایی شد', ''],
        story: ['شیء شناسایی شد', ''],
        unmatched: ['شیء شناسایی نشد.', 'لطفاً زاویه دوربین را تغییر دهید.'],
        network: ['خطای اتصال', 'اتصال اینترنت را بررسی کنید و دوباره تلاش کنید.'],
        denied: ['عدم دسترسی به دوربین', 'اجازهٔ استفاده از دوربین را در تنظیمات مرورگر بدهید.'],
        error: ['خطا در شناسایی', 'دوباره تلاش کنید.'],
        empty: ['هنوز شیئی برای شناسایی آماده نیست.', ''],
        videoError: ['ویدیو بارگذاری نشد', 'اتصال را بررسی کنید و «پخش دوباره» را بزنید.'],
    };

    let stream = null;
    let guide = null;
    let running = false;
    let timer = null;
    let scanStartedAt = 0;
    let unmatchedReported = false;
    let streak = { id: null, count: 0 };
    let current = null;
    let playReported = false;
    let currentItem = null;

    function setState(state) {
        const [title, hint] = MESSAGES[state];
        statusBox.dataset.state = state;
        stage.dataset.state = state;
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
        camera.hidden = true;
        $('reticle').hidden = true;
    }

    function resetStory() {
        story.pause();
        story.removeAttribute('src');
        story.load();
        story.hidden = true;
        $('loading').hidden = true;
        $('controls').hidden = true;
        $('replay').hidden = true;
        $('sheet').hidden = true;
        playReported = false;
    }

    /** Opening the sound on the very tap that starts the scan lets the story play with audio later. */
    function unlockAudio() {
        try {
            const Context = window.AudioContext || window.webkitAudioContext;
            if (Context) {
                new Context().resume();
            }
        } catch (error) {
            // Audio is a nicety; the story still plays muted if it stays locked.
        }
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
        unlockAudio();
        resetStory();
        $('intro').hidden = true;
        streak = { id: null, count: 0 };
        unmatchedReported = false;
        current = null;
        setState('loading');

        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
        } catch (error) {
            setState('denied');
            $('intro').hidden = false;

            return;
        }

        camera.srcObject = stream;
        camera.hidden = false;
        $('reticle').hidden = false;
        await camera.play().catch(() => {});

        try {
            await fetchGuide();
        } catch (error) {
            stopCamera();
            setState('network');
            $('intro').hidden = false;

            return;
        }

        if (!guide.objects.length) {
            stopCamera();
            setState('empty');
            $('intro').hidden = false;

            return;
        }

        try {
            await loadEngine();
        } catch (error) {
            stopCamera();
            setState('error');
            $('intro').hidden = false;
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
            if (camera.readyState >= 2 && camera.videoWidth > 0) {
                const vector = await embed(camera, camera.videoWidth, camera.videoHeight);
                const outcome = rank(vector, guide.objects, threshold, margin);

                if (showDebug && outcome.best) {
                    $('debug').hidden = false;
                    $('debug').textContent = `${outcome.best.object.slug} ${outcome.best.score.toFixed(3)} gap ${outcome.gap.toFixed(3)}`;
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
            $('intro').hidden = false;
            reportEvent({ type: 'scan_error' });

            return;
        }

        if (running) {
            timer = setTimeout(tick, interval);
        }
    }

    function found(object, score) {
        stopCamera();
        current = object;
        setState('matched');
        reportEvent({
            type: 'scan_matched',
            object_id: object.id,
            confidence: Math.max(0, Math.min(1, score)),
            latency_ms: Math.round(performance.now() - scanStartedAt),
        });
        renderSheet(object);
        $('controls').hidden = false;

        if (object.videos.length) {
            playStory(object.videos[0]);
        }
    }

    /** Loads the story behind a "loading" curtain, then starts it by itself, with sound when the browser allows. */
    function playStory(item) {
        currentItem = item;
        setState('story');
        $('loadingTitle').textContent = current?.title ?? '';
        $('loading').hidden = false;
        $('replay').hidden = true;
        story.hidden = true;
        playReported = false;

        let settled = false;
        const finish = () => {
            if (settled) {
                return;
            }
            settled = true;
            clearTimeout(giveUp);
            story.removeEventListener('canplay', onReady);
            story.removeEventListener('error', onFail);
        };
        const onReady = async () => {
            finish();
            $('loading').hidden = true;
            story.hidden = false;
            story.controls = false;
            story.muted = false;
            try {
                await story.play();
            } catch (error) {
                story.muted = true;
                try {
                    await story.play();
                } catch (second) {
                    story.controls = true;
                }
            }
            updateSound();
        };
        const onFail = () => {
            finish();
            $('loading').hidden = true;
            story.hidden = true;
            setState('videoError');
            $('replay').hidden = false;
        };
        const giveUp = setTimeout(onFail, 15000);

        story.addEventListener('canplay', onReady);
        story.addEventListener('error', onFail);
        story.poster = item.poster_url ?? '';
        story.src = item.url;
        story.load();
    }

    function updateSound() {
        const button = $('sound');
        button.textContent = story.muted ? 'پخش با صدا' : 'بی‌صدا';
        button.classList.toggle('pulse', story.muted);
    }

    story.addEventListener('playing', () => {
        if (!playReported && current) {
            playReported = true;
            reportEvent({ type: 'video_play', object_id: current.id });
        }
    });
    story.addEventListener('ended', () => { $('replay').hidden = false; });

    $('sound').addEventListener('click', () => {
        story.muted = !story.muted;
        if (story.paused) {
            story.play().catch(() => {});
        }
        updateSound();
    });
    $('replay').addEventListener('click', () => {
        $('replay').hidden = true;
        if (!story.src || story.error || stage.dataset.state === 'videoError') {
            playStory(currentItem);

            return;
        }
        story.currentTime = 0;
        story.play().catch(() => {});
    });

    function renderSheet(object) {
        const sheet = $('sheet');
        sheet.replaceChildren();

        const heading = document.createElement('h2');
        heading.textContent = object.title;
        sheet.append(heading);

        if (object.zone) {
            const zone = document.createElement('span');
            zone.className = 'zone';
            zone.textContent = object.zone;
            sheet.append(zone);
        }

        if (object.description) {
            const text = document.createElement('p');
            text.textContent = object.description;
            sheet.append(text);
        }

        if (!object.videos.length) {
            const note = document.createElement('p');
            note.className = 'hint';
            note.textContent = 'برای این شیء هنوز ویدیویی منتشر نشده است.';
            sheet.append(note);
        } else if (object.videos.length > 1) {
            const list = document.createElement('div');
            list.className = 'others';
            for (const item of object.videos) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'secondary';
                button.textContent = `پخش: ${item.title ?? 'ویدیو'}`;
                button.addEventListener('click', () => playStory(item));
                list.append(button);
            }
            sheet.append(list);
        }

        sheet.hidden = false;
    }

    $('start').addEventListener('click', begin);
    $('again').addEventListener('click', begin);
    document.addEventListener('visibilitychange', () => {
        if (document.hidden && running) {
            stopCamera();
            setState('ready');
            $('intro').hidden = false;
        }
    });
    setState('ready');
</script>

@include('partials.customer-app-pwa-register')
</body>
</html>
