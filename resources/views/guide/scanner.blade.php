<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0c0a09">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>راهنمای هوشمند — گنجنامه</title>

    @include('partials.customer-app-pwa-head')

    @fonts

    <style>
        :root {
            color-scheme: dark;
            --bg: #0c0a09;
            --stone: #1c1917;
            --amber: #f97316;
            --amber-soft: #fdba74;
            --amber-glow: rgb(249 115 22 / .35);
            --ok: #4ade80;
            --bad: #fca5a5;
            --text: #fafaf9;
            --text-dim: #d6d3d1;
            --glass: rgb(28 25 23 / .62);
            --glass-strong: rgb(28 25 23 / .82);
            --glass-line: rgb(255 255 255 / .14);
            --ease: cubic-bezier(.22, 1, .36, 1);
        }

        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body { height: 100%; }
        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family: 'Vazirmatn', ui-sans-serif, system-ui, sans-serif;
            overflow: hidden;
        }

        .app {
            position: fixed;
            inset: 0;
            max-width: 34rem;
            margin: 0 auto;
            overflow: hidden;
            background:
                radial-gradient(60% 40% at 80% 0%, rgb(249 115 22 / .22), transparent 70%),
                radial-gradient(50% 35% at 10% 100%, rgb(180 83 9 / .25), transparent 70%),
                var(--bg);
        }

        /* ---------- Layers ---------- */
        .layer { position: absolute; inset: 0; }
        video.layer { width: 100%; height: 100%; }
        #camera { object-fit: cover; }
        #story { object-fit: contain; background: #000; z-index: 2; }
        #cover { width: 100%; height: 100%; object-fit: cover; z-index: 1; opacity: .55; }
        #freeze { width: 100%; height: 100%; object-fit: cover; filter: blur(18px) brightness(.55) saturate(1.2); transform: scale(1.1); z-index: 3; }

        .vignette {
            z-index: 4;
            pointer-events: none;
            background: linear-gradient(to bottom, rgb(0 0 0 / .6), transparent 22%, transparent 55%, rgb(0 0 0 / .65));
        }

        /* ---------- Top bar ---------- */
        .top {
            position: absolute;
            inset-inline: 0;
            top: 0;
            z-index: 10;
            display: flex;
            align-items: center;
            gap: .6rem;
            padding: max(.75rem, env(safe-area-inset-top)) .9rem .6rem;
        }
        .glass {
            background: var(--glass);
            border: 1px solid var(--glass-line);
            -webkit-backdrop-filter: blur(14px) saturate(1.3);
            backdrop-filter: blur(14px) saturate(1.3);
        }
        .icon-btn {
            width: 2.75rem;
            height: 2.75rem;
            flex: none;
            display: grid;
            place-items: center;
            border-radius: 50%;
            color: var(--text);
            text-decoration: none;
            cursor: pointer;
            padding: 0;
            font: inherit;
            transition: transform .18s var(--ease), background .18s;
        }
        .icon-btn:active { transform: scale(.92); }
        .icon-btn svg { width: 1.3rem; height: 1.3rem; }
        .title-pill {
            display: flex;
            align-items: center;
            gap: .5rem;
            padding: .5rem .9rem;
            border-radius: 99px;
            font-size: .88rem;
            font-weight: 700;
            min-width: 0;
        }
        .title-pill small {
            font-weight: 600;
            font-size: .68rem;
            color: #1c1917;
            background: var(--amber-soft);
            border-radius: 99px;
            padding: .1rem .5rem;
        }

        a:focus-visible, button:focus-visible { outline: 3px solid var(--amber-soft); outline-offset: 3px; }

        /* ---------- Intro ---------- */
        .intro {
            position: absolute;
            inset: 0;
            z-index: 8;
            display: grid;
            place-items: center;
            padding: 5rem 1.1rem 1.5rem;
            overflow-y: auto;
        }
        .intro-card {
            width: 100%;
            padding: 1.6rem 1.3rem 1.3rem;
            border-radius: 1.75rem;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: .9rem;
            animation: rise .6s var(--ease) both;
        }
        .mark {
            width: 4.6rem;
            height: 4.6rem;
            border-radius: 1.4rem;
            display: grid;
            place-items: center;
            background: linear-gradient(145deg, #fb923c, #c2410c);
            box-shadow: 0 0 0 .45rem rgb(249 115 22 / .14), 0 1rem 2.5rem var(--amber-glow);
        }
        .mark svg { width: 2.3rem; height: 2.3rem; color: #fff; }
        .intro h2 { margin: 0; font-size: 1.25rem; line-height: 1.7; text-wrap: balance; }
        .intro p { margin: 0; color: var(--text-dim); font-size: .88rem; line-height: 2; }
        .steps { list-style: none; margin: .2rem 0 0; padding: 0; width: 100%; display: flex; flex-direction: column; gap: .55rem; text-align: start; }
        .steps li {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .65rem .8rem;
            border-radius: 1rem;
            background: rgb(255 255 255 / .06);
            font-size: .86rem;
            line-height: 1.7;
        }
        .steps .n {
            flex: none;
            width: 1.9rem;
            height: 1.9rem;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: rgb(249 115 22 / .2);
            color: var(--amber-soft);
            font-weight: 800;
            font-size: .85rem;
        }
        .cta {
            width: 100%;
            min-height: 3.2rem;
            border: 0;
            border-radius: 1rem;
            font: inherit;
            font-weight: 800;
            font-size: 1rem;
            color: #fff;
            cursor: pointer;
            background: linear-gradient(135deg, #fb923c, #ea580c);
            box-shadow: 0 .6rem 1.8rem var(--amber-glow);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            transition: transform .18s var(--ease), box-shadow .18s;
        }
        .cta:active { transform: scale(.98); }
        .cta svg { width: 1.2rem; height: 1.2rem; }
        .privacy { font-size: .74rem; color: var(--text-dim); opacity: .85; display: flex; gap: .35rem; align-items: center; justify-content: center; }
        .privacy svg { width: .9rem; height: .9rem; flex: none; }

        /* ---------- Reticle ---------- */
        .reticle {
            position: absolute;
            inset: 19% 13% 27%;
            z-index: 5;
            pointer-events: none;
            --c: rgb(255 255 255 / .92);
            transition: inset .5s var(--ease), filter .3s;
            background:
                linear-gradient(var(--c), var(--c)) top left / 2.2rem 4px no-repeat,
                linear-gradient(var(--c), var(--c)) top left / 4px 2.2rem no-repeat,
                linear-gradient(var(--c), var(--c)) top right / 2.2rem 4px no-repeat,
                linear-gradient(var(--c), var(--c)) top right / 4px 2.2rem no-repeat,
                linear-gradient(var(--c), var(--c)) bottom left / 2.2rem 4px no-repeat,
                linear-gradient(var(--c), var(--c)) bottom left / 4px 2.2rem no-repeat,
                linear-gradient(var(--c), var(--c)) bottom right / 2.2rem 4px no-repeat,
                linear-gradient(var(--c), var(--c)) bottom right / 4px 2.2rem no-repeat;
            filter: drop-shadow(0 0 .5rem rgb(0 0 0 / .5));
        }
        .reticle .sweep {
            position: absolute;
            inset-inline: 4%;
            height: 3px;
            border-radius: 3px;
            background: linear-gradient(to right, transparent, var(--amber), transparent);
            box-shadow: 0 0 1rem 2px var(--amber-glow);
            animation: sweep 2.4s ease-in-out infinite alternate;
        }
        .reticle.lock { --c: var(--amber-soft); inset: 22% 16% 30%; }
        .app[data-state="matched"] .reticle, .app[data-state="story"] .reticle { --c: var(--ok); inset: 22% 16% 30%; }
        .app[data-state="matched"] .sweep, .app[data-state="story"] .sweep { display: none; }
        @keyframes sweep { from { top: 4%; } to { top: 94%; } }

        /* ---------- Status pill ---------- */
        .status-wrap {
            position: absolute;
            inset-inline: 0;
            bottom: 0;
            z-index: 9;
            display: flex;
            justify-content: center;
            padding: 0 1rem max(1.4rem, env(safe-area-inset-bottom));
            pointer-events: none;
        }
        .status {
            display: flex;
            align-items: center;
            gap: .7rem;
            padding: .65rem 1.1rem;
            border-radius: 1.2rem;
            max-width: 100%;
            font-weight: 700;
            font-size: .95rem;
            line-height: 1.6;
        }
        .status .dot { flex: none; width: .65rem; height: .65rem; border-radius: 50%; background: var(--text-dim); }
        .status small { display: block; font-weight: 400; font-size: .76rem; color: var(--text-dim); }
        .app[data-state="scanning"] .dot { background: var(--amber); animation: pulse 1.3s ease-in-out infinite; }
        .app[data-state="loading"] .dot { background: var(--amber-soft); animation: pulse 1.3s ease-in-out infinite; }
        .app[data-state="matched"] .dot, .app[data-state="story"] .dot { background: var(--ok); }
        .app[data-state="unmatched"] .dot { background: var(--amber-soft); }
        .app[data-state="error"] .dot, .app[data-state="denied"] .dot, .app[data-state="network"] .dot, .app[data-state="videoError"] .dot { background: var(--bad); }
        .app[data-state="error"] .status, .app[data-state="denied"] .status, .app[data-state="network"] .status, .app[data-state="videoError"] .status { color: var(--bad); }
        @keyframes pulse { 50% { box-shadow: 0 0 0 .45rem var(--amber-glow); transform: scale(1.05); } }

        /* ---------- Loading the story ---------- */
        .loading {
            position: absolute;
            inset: 0;
            z-index: 6;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1.1rem;
            padding: 2rem;
            text-align: center;
        }
        .loading .label { font-weight: 800; font-size: 1.1rem; }
        .loading .name { color: var(--amber-soft); font-size: .95rem; }
        .bars { display: flex; align-items: flex-end; gap: .28rem; height: 2.4rem; }
        .bars i {
            width: .3rem;
            height: 100%;
            border-radius: .3rem;
            background: var(--amber);
            transform-origin: bottom;
            animation: bar 1s ease-in-out infinite;
        }
        .bars i:nth-child(2) { animation-delay: .12s; }
        .bars i:nth-child(3) { animation-delay: .24s; }
        .bars i:nth-child(4) { animation-delay: .36s; }
        .bars i:nth-child(5) { animation-delay: .48s; }
        @keyframes bar { 0%, 100% { transform: scaleY(.25); } 50% { transform: scaleY(1); } }

        /* ---------- Info sheet ---------- */
        .sheet {
            position: absolute;
            inset-inline: 0;
            bottom: 0;
            z-index: 12;
            border-radius: 1.75rem 1.75rem 0 0;
            border-bottom: 0;
            background: var(--glass-strong);
            padding: .5rem 1.1rem max(1rem, env(safe-area-inset-bottom));
            display: flex;
            flex-direction: column;
            gap: .75rem;
            max-height: 62%;
            animation: slide .5s var(--ease) both;
        }
        .grab { align-self: center; width: 2.8rem; height: .3rem; border-radius: 1rem; background: rgb(255 255 255 / .3); border: 0; padding: .4rem 0; background-clip: content-box; cursor: pointer; }
        .sheet-body { overflow-y: auto; display: flex; flex-direction: column; gap: .6rem; min-height: 0; }
        .sheet h2 { margin: 0; font-size: 1.2rem; line-height: 1.7; }
        .chip { align-self: flex-start; font-size: .74rem; color: var(--amber-soft); border: 1px solid rgb(253 186 116 / .4); border-radius: 99px; padding: .1rem .7rem; }
        .sheet p { margin: 0; line-height: 2.05; font-size: .9rem; color: var(--text-dim); white-space: pre-line; }
        .sheet.collapsed .desc { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        .sheet .hint { font-size: .84rem; opacity: .8; }
        .others { display: flex; flex-direction: column; gap: .45rem; }
        .others button {
            min-height: 2.75rem;
            text-align: start;
            font: inherit;
            font-size: .86rem;
            color: var(--text);
            background: rgb(255 255 255 / .08);
            border: 1px solid var(--glass-line);
            border-radius: .9rem;
            padding: .5rem .9rem;
            cursor: pointer;
        }
        .actions { display: flex; align-items: center; gap: .55rem; }
        .actions .cta { min-height: 2.9rem; font-size: .92rem; box-shadow: none; }
        .actions .icon-btn { background: rgb(255 255 255 / .12); border: 1px solid var(--glass-line); }
        .icon-btn.attn { animation: pulse 1.4s ease-in-out infinite; background: var(--amber); }

        .debug { position: absolute; top: 4.2rem; inset-inline: 0; z-index: 11; text-align: center; font-size: .7rem; color: var(--text-dim); direction: ltr; margin: 0; pointer-events: none; }
        [hidden] { display: none !important; }

        @keyframes rise { from { opacity: 0; transform: translateY(1.2rem); } }
        @keyframes slide { from { transform: translateY(100%); } }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; }
        }
    </style>
</head>
<body>
<div class="app" id="app" data-state="ready">
    <img class="layer" id="cover" alt="" hidden>
    <video class="layer" id="camera" playsinline muted autoplay hidden></video>
    <video class="layer" id="story" playsinline preload="auto" hidden></video>
    <canvas class="layer" id="freeze" hidden></canvas>
    <div class="layer vignette"></div>

    <header class="top">
        <a class="icon-btn glass" href="{{ route('customer-app') }}" aria-label="بازگشت به اپ">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
        </a>
        <h1 class="title-pill glass" style="margin:0">
            راهنمای هوشمند گنجنامه
            @if ($staffPreview)
                <small>حالت آزمایش</small>
            @endif
        </h1>
    </header>

    <div class="reticle" id="reticle" hidden><i class="sweep"></i></div>

    <section class="intro" id="intro" aria-labelledby="introTitle">
        <div class="intro-card glass">
            <div class="mark" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8V6a2 2 0 0 1 2-2h2M16 4h2a2 2 0 0 1 2 2v2M20 16v2a2 2 0 0 1-2 2h-2M8 20H6a2 2 0 0 1-2-2v-2"/><circle cx="12" cy="12" r="3.2"/></svg>
            </div>
            <h2 id="introTitle">داستان هر اثر را زنده ببینید</h2>
            <p>دوربین را روی یکی از اشیای موزه بگیرید تا همان‌جا داستانش با ویدیو و صدا شروع شود.</p>
            <ol class="steps">
                <li><span class="n">۱</span>دوربین را روی اثر بگیرید</li>
                <li><span class="n">۲</span>چند ثانیه ثابت نگه دارید تا شناسایی شود</li>
                <li><span class="n">۳</span>داستان به‌طور خودکار پخش می‌شود</li>
            </ol>
            <button class="cta" id="start" type="button">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 8V6a2 2 0 0 1 2-2h2M16 4h2a2 2 0 0 1 2 2v2M20 16v2a2 2 0 0 1-2 2h-2M8 20H6a2 2 0 0 1-2-2v-2"/></svg>
                شروع اسکن
            </button>
            <div class="privacy">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
                تصویر دوربین فقط روی گوشی شما می‌ماند و جایی فرستاده نمی‌شود.
            </div>
        </div>
    </section>

    <div class="loading" id="loading" hidden role="status" aria-live="polite" aria-busy="true">
        <div class="bars" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></div>
        <div class="label">در حال لود کردن داستان…</div>
        <div class="name" id="loadingTitle"></div>
    </div>

    <p class="debug" id="debug" hidden></p>

    <div class="status-wrap" id="statusWrap" hidden>
        <div class="status glass" id="status" role="status" aria-live="polite"></div>
    </div>

    <section class="sheet glass collapsed" id="sheet" hidden aria-label="اطلاعات شیء">
        <button class="grab" id="grab" type="button" aria-expanded="false" aria-label="نمایش توضیحات بیشتر"></button>
        <div class="sheet-body" id="sheetBody"></div>
        <div class="actions">
            <button class="icon-btn" id="sound" type="button" aria-label="پخش با صدا" hidden>
                <svg id="soundOn" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H3v6h3l5 4z"/><path d="M15.5 8.5a5 5 0 0 1 0 7M18.5 5.5a9 9 0 0 1 0 13"/></svg>
                <svg id="soundOff" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" hidden><path d="M11 5 6 9H3v6h3l5 4z"/><path d="m16 9 5 6M21 9l-5 6"/></svg>
            </button>
            <button class="icon-btn" id="replay" type="button" aria-label="پخش دوباره" hidden>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/></svg>
            </button>
            <button class="cta" id="again" type="button">اسکن شیء بعدی</button>
        </div>
    </section>
</div>

<script type="module">
    import { loadEngine, embed, rank, reportEvent } from '/guide/guide-engine.js?v=1';

    const $ = (id) => document.getElementById(id);
    const app = $('app');
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
    let currentItem = null;
    let playReported = false;

    function setState(state) {
        const [title, hint] = MESSAGES[state];
        app.dataset.state = state;
        const dot = document.createElement('span');
        dot.className = 'dot';
        const text = document.createElement('div');
        text.textContent = title;
        if (hint) {
            const small = document.createElement('small');
            small.textContent = hint;
            text.append(small);
        }
        statusBox.replaceChildren(dot, text);
        $('statusWrap').hidden = $('sheet').hidden === false && (state === 'matched' || state === 'story') ? true : state === 'ready';
    }

    /** Keeps the last camera picture, blurred, as the backdrop while the story loads. */
    function freezeFrame() {
        if (!camera.videoWidth) {
            return;
        }
        const canvas = $('freeze');
        canvas.width = 180;
        canvas.height = Math.round(180 * camera.videoHeight / camera.videoWidth);
        canvas.getContext('2d').drawImage(camera, 0, 0, canvas.width, canvas.height);
        canvas.hidden = false;
    }

    function stopCamera() {
        running = false;
        clearTimeout(timer);
        stream?.getTracks().forEach((track) => track.stop());
        stream = null;
        camera.hidden = true;
        $('reticle').hidden = true;
        $('reticle').classList.remove('lock');
    }

    function resetStory() {
        story.pause();
        story.removeAttribute('src');
        story.load();
        story.hidden = true;
        $('freeze').hidden = true;
        $('cover').hidden = true;
        $('loading').hidden = true;
        $('sheet').hidden = true;
        $('replay').hidden = true;
        playReported = false;
    }

    /** Opening the audio on the very tap that starts the scan lets the story play with sound later. */
    function unlockAudio() {
        try {
            const Context = window.AudioContext || window.webkitAudioContext;
            if (Context) {
                new Context().resume();
            }
        } catch (error) {
            // Sound is a nicety; the story still plays muted if it stays locked.
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

    function backToIntro(state) {
        stopCamera();
        setState(state);
        $('intro').hidden = false;
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
            backToIntro('denied');

            return;
        }

        camera.srcObject = stream;
        camera.hidden = false;
        $('reticle').hidden = false;
        await camera.play().catch(() => {});

        try {
            await fetchGuide();
        } catch (error) {
            backToIntro('network');

            return;
        }

        if (!guide.objects.length) {
            backToIntro('empty');

            return;
        }

        try {
            await loadEngine();
        } catch (error) {
            backToIntro('error');
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
                    $('reticle').classList.add('lock');

                    if (streak.count >= 2 && running) {
                        found(outcome.best.object, outcome.best.score);

                        return;
                    }
                } else {
                    streak = { id: null, count: 0 };
                    $('reticle').classList.remove('lock');
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
            backToIntro('error');
            reportEvent({ type: 'scan_error' });

            return;
        }

        if (running) {
            timer = setTimeout(tick, interval);
        }
    }

    function found(object, score) {
        freezeFrame();
        stopCamera();
        current = object;
        reportEvent({
            type: 'scan_matched',
            object_id: object.id,
            confidence: Math.max(0, Math.min(1, score)),
            latency_ms: Math.round(performance.now() - scanStartedAt),
        });
        renderSheet(object);
        $('sheet').hidden = false;
        setState('matched');

        if (object.videos.length) {
            playStory(object.videos[0]);
        } else {
            $('freeze').hidden = true;
            if (object.cover_url) {
                $('cover').src = object.cover_url;
                $('cover').hidden = false;
            }
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
            $('freeze').hidden = true;
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
            $('statusWrap').hidden = false;
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
        button.hidden = false;
        $('soundOn').hidden = story.muted;
        $('soundOff').hidden = !story.muted;
        button.setAttribute('aria-label', story.muted ? 'پخش با صدا' : 'بی‌صدا کردن');
        button.classList.toggle('attn', story.muted);
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
        if (!story.src || story.error || app.dataset.state === 'videoError') {
            playStory(currentItem);

            return;
        }
        story.currentTime = 0;
        story.play().catch(() => {});
    });
    $('grab').addEventListener('click', () => {
        const collapsed = $('sheet').classList.toggle('collapsed');
        $('grab').setAttribute('aria-expanded', String(!collapsed));
        $('grab').setAttribute('aria-label', collapsed ? 'نمایش توضیحات بیشتر' : 'جمع کردن توضیحات');
    });

    function renderSheet(object) {
        const body = $('sheetBody');
        body.replaceChildren();
        $('sound').hidden = true;
        $('sheet').classList.add('collapsed');
        $('grab').setAttribute('aria-expanded', 'false');

        const heading = document.createElement('h2');
        heading.textContent = object.title;
        body.append(heading);

        if (object.zone) {
            const zone = document.createElement('span');
            zone.className = 'chip';
            zone.textContent = object.zone;
            body.append(zone);
        }

        if (object.description) {
            const text = document.createElement('p');
            text.className = 'desc';
            text.textContent = object.description;
            body.append(text);
        }

        if (!object.videos.length) {
            const note = document.createElement('p');
            note.className = 'hint';
            note.textContent = 'برای این شیء هنوز ویدیویی منتشر نشده است.';
            body.append(note);
        } else if (object.videos.length > 1) {
            const list = document.createElement('div');
            list.className = 'others';
            for (const item of object.videos) {
                const button = document.createElement('button');
                button.type = 'button';
                button.textContent = `پخش: ${item.title ?? 'ویدیو'}`;
                button.addEventListener('click', () => playStory(item));
                list.append(button);
            }
            body.append(list);
        }
    }

    $('start').addEventListener('click', begin);
    $('again').addEventListener('click', begin);
    document.addEventListener('visibilitychange', () => {
        if (document.hidden && running) {
            backToIntro('ready');
        }
    });
    setState('ready');
</script>

@include('partials.customer-app-pwa-register')
</body>
</html>
