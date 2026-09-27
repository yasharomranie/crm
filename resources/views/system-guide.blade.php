<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>راهنمای سامانه FarsiWP</title>
<style>
  /* Self-hosted, not a Google Fonts CDN link - this app is hosted for
     users in Iran, where third-party CDNs can be slow or filtered (see
     .ai/rules/general.md). Same variable-weight file this app's own
     Vite build already produces (public/build/assets/vazirmatn-variable-
     normal-*.woff2), just copied under a stable name so a later
     `npm run build` re-hashing that file doesn't break this page. */
  @font-face {
    font-family: 'Vazirmatn';
    font-style: normal;
    font-weight: 100 900;
    font-display: swap;
    src: url('/system-guide-assets/fonts/vazirmatn.woff2') format('woff2');
  }

  * { box-sizing: border-box; }

  html, body { margin: 0; padding: 0; }

  :root {
    --paper: #fbf9f6;
    --paper-2: #f4ece0;
    --paper-3: #efe4d2;
    --ink: #1c1917;
    --ink-soft: #57534e;
    --ink-faint: #8a8177;
    --border: rgba(28, 25, 23, 0.12);
    --border-soft: rgba(28, 25, 23, 0.07);
    --amber-100: #fde68a;
    --amber-500: #f59e0b;
    --amber-600: #d97706;
    --amber-900: #78350f;
    --amber-ink: #451a03;
    --shadow: 0 18px 40px -20px rgb(28 25 23 / 0.28);
    --shadow-sm: 0 8px 20px -12px rgb(28 25 23 / 0.22);
    color-scheme: light;
  }

  @media (prefers-color-scheme: dark) {
    :root:not([data-theme="light"]) {
      --paper: #171310;
      --paper-2: #221c15;
      --paper-3: #2c2318;
      --ink: #f3ece0;
      --ink-soft: #cdbfa9;
      --ink-faint: #8f8271;
      --border: rgba(253, 230, 138, 0.16);
      --border-soft: rgba(253, 230, 138, 0.09);
      --amber-100: #fde68a;
      --amber-500: #f6a723;
      --amber-600: #f0930f;
      --amber-900: #fbbf6a;
      --amber-ink: #2a1204;
      --shadow: 0 18px 40px -18px rgb(0 0 0 / 0.55);
      --shadow-sm: 0 8px 20px -12px rgb(0 0 0 / 0.5);
      color-scheme: dark;
    }
  }

  :root[data-theme="dark"] {
    --paper: #171310;
    --paper-2: #221c15;
    --paper-3: #2c2318;
    --ink: #f3ece0;
    --ink-soft: #cdbfa9;
    --ink-faint: #8f8271;
    --border: rgba(253, 230, 138, 0.16);
    --border-soft: rgba(253, 230, 138, 0.09);
    --amber-100: #fde68a;
    --amber-500: #f6a723;
    --amber-600: #f0930f;
    --amber-900: #fbbf6a;
    --amber-ink: #2a1204;
    --shadow: 0 18px 40px -18px rgb(0 0 0 / 0.55);
    --shadow-sm: 0 8px 20px -12px rgb(0 0 0 / 0.5);
    color-scheme: dark;
  }

  body {
    background: var(--paper);
    color: var(--ink);
  }

  .page {
    font-family: 'Vazirmatn', Tahoma, Arial, sans-serif;
    direction: rtl;
    max-width: 920px;
    margin: 0 auto;
    padding-inline: 20px;
    padding-block: calc(2.5rem + env(safe-area-inset-top, 0px)) calc(4rem + env(safe-area-inset-bottom, 0px));
    line-height: 1.85;
  }

  h1, h2, h3 { text-wrap: balance; margin: 0; }

  .mono {
    font-family: ui-monospace, 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
    direction: ltr;
    unicode-bidi: isolate;
  }

  /* ---------- Top identity bar ---------- */
  .topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    padding-block-end: 2.25rem;
    border-bottom: 1px solid var(--border-soft);
    margin-bottom: 2.75rem;
  }
  .brandmark { display: flex; align-items: center; gap: .6rem; }
  .brandmark svg { width: 30px; height: 30px; display: block; }
  .brandmark .word { font-weight: 800; font-size: 1.05rem; line-height: 1; direction: ltr; unicode-bidi: isolate; }
  .brandmark .word b { color: var(--amber-600); font-weight: 800; }
  .topbar .doc-kicker {
    font-family: ui-monospace, 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
    font-size: .68rem;
    letter-spacing: .06em;
    color: var(--ink-faint);
    direction: ltr;
    unicode-bidi: isolate;
    text-transform: uppercase;
  }

  /* ---------- Cover ---------- */
  .cover { padding-block: 1rem 3rem; }
  .cover .mascot-big { width: 108px; height: 108px; display: block; margin-bottom: 1.6rem; filter: drop-shadow(0 14px 24px rgb(217 119 6 / .25)); }
  .cover .eyebrow {
    display: inline-flex; align-items: center; gap: .45rem;
    font-family: ui-monospace, 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
    font-size: .72rem; letter-spacing: .05em;
    color: var(--amber-600);
    background: var(--paper-2);
    border: 1px solid var(--border);
    border-radius: 999px;
    padding: .3rem .8rem;
    margin-bottom: 1.1rem;
  }
  .cover .eyebrow::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: var(--amber-500); }
  .cover h1 {
    font-size: clamp(2.1rem, 5vw, 3rem);
    font-weight: 800;
    letter-spacing: -.01em;
  }
  .cover .tagline-en {
    direction: ltr; unicode-bidi: isolate;
    font-family: ui-monospace, 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
    font-size: .78rem;
    color: var(--ink-faint);
    margin-top: .6rem;
    letter-spacing: .02em;
  }
  .cover p.lede {
    max-width: 62ch;
    color: var(--ink-soft);
    font-size: 1.05rem;
    margin-top: 1.4rem;
  }
  .cover .meta-row {
    display: flex; flex-wrap: wrap; gap: .5rem 1.4rem;
    margin-top: 1.6rem;
    font-size: .82rem;
    color: var(--ink-faint);
  }
  .cover .meta-row span b { color: var(--ink-soft); font-weight: 600; }

  /* ---------- Table of contents ---------- */
  .toc {
    background: var(--paper-2);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 1.6rem 1.7rem;
    margin-block: 3rem;
  }
  .toc h2 { font-size: .82rem; letter-spacing: .04em; color: var(--ink-faint); text-transform: uppercase; font-family: ui-monospace, 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; margin-bottom: 1rem; }
  .toc ol { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: 1fr 1fr; gap: .35rem 1.5rem; counter-reset: toc; }
  @media (max-width: 640px) { .toc ol { grid-template-columns: 1fr; } }
  .toc li { counter-increment: toc; }
  .toc a {
    display: flex; align-items: baseline; gap: .6rem;
    color: var(--ink); text-decoration: none;
    padding: .5rem 0;
    border-bottom: 1px dashed var(--border-soft);
    font-size: .93rem;
  }
  .toc a:hover { color: var(--amber-600); }
  .toc a::before {
    content: counter(toc, decimal-leading-zero);
    font-family: ui-monospace, 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
    font-size: .74rem;
    color: var(--amber-600);
    flex-shrink: 0;
  }

  /* ---------- Chapters ---------- */
  section.chapter { padding-block: 2.6rem; border-top: 1px solid var(--border-soft); scroll-margin-top: 1.5rem; }
  section.chapter:first-of-type { border-top: none; }
  .chapter-head { display: flex; align-items: flex-start; gap: 1.1rem; margin-bottom: 1.3rem; }
  .chapter-icon {
    flex-shrink: 0; width: 52px; height: 52px; border-radius: 16px;
    display: flex; align-items: center; justify-content: center;
    background: linear-gradient(155deg, var(--amber-100), var(--amber-500));
    box-shadow: var(--shadow-sm);
  }
  .chapter-icon svg { width: 26px; height: 26px; stroke: var(--amber-ink); }
  .chapter-num {
    font-family: ui-monospace, 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; font-size: .74rem; color: var(--amber-600);
    letter-spacing: .05em; display: block; margin-bottom: .3rem;
  }
  .chapter-head h2 { font-size: 1.5rem; font-weight: 800; }
  .chapter-body p { color: var(--ink-soft); max-width: 68ch; }
  .chapter-body p.lede { font-size: 1.02rem; color: var(--ink); margin-bottom: 1.1rem; }

  .feature-grid {
    display: grid; grid-template-columns: repeat(2, 1fr); gap: .7rem;
    margin-top: 1.2rem;
  }
  @media (max-width: 640px) { .feature-grid { grid-template-columns: 1fr; } }
  .feature-card {
    background: var(--paper-2);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: .95rem 1.1rem;
  }
  .feature-card .name { font-weight: 700; font-size: .92rem; display: flex; align-items: center; gap: .5rem; }
  .feature-card .name::before {
    content: ''; width: 6px; height: 6px; border-radius: 50%; background: var(--amber-500); flex-shrink: 0;
  }
  .feature-card .desc { color: var(--ink-faint); font-size: .82rem; margin-top: .3rem; line-height: 1.6; }

  .callout {
    margin-top: 1.4rem;
    border-radius: 16px;
    border: 1px solid var(--border);
    background: linear-gradient(180deg, var(--paper-2), var(--paper-3));
    padding: 1.1rem 1.3rem;
    display: flex; gap: .9rem; align-items: flex-start;
  }
  .callout svg { width: 20px; height: 20px; flex-shrink: 0; stroke: var(--amber-600); margin-top: .15rem; }
  .callout .label { font-family: ui-monospace, 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; font-size: .68rem; letter-spacing: .04em; color: var(--amber-600); text-transform: uppercase; margin-bottom: .25rem; display: block; }
  .callout p { margin: 0; color: var(--ink); font-size: .92rem; }

  /* ---------- Step-by-step screenshots ---------- */
  .howto { margin-top: 1.8rem; }
  .howto-label {
    font-family: ui-monospace, 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; font-size: .7rem; letter-spacing: .06em;
    color: var(--ink-faint); text-transform: uppercase; margin-bottom: .8rem; display: flex; align-items: center; gap: .5rem;
  }
  .howto-label::before { content: ''; width: 14px; height: 1px; background: var(--border); }
  .howto-label::after { content: ''; flex: 1; height: 1px; background: var(--border); }
  .shots { display: flex; flex-direction: column; gap: 1.2rem; }
  .shot {
    margin: 0; border: 1px solid var(--border); border-radius: 16px; overflow: hidden;
    background: var(--paper-2); box-shadow: var(--shadow-sm);
  }
  .shot img { display: block; width: 100%; height: auto; border-block-end: 1px solid var(--border-soft); }
  .shot figcaption { padding: .7rem 1.05rem; display: flex; gap: .7rem; align-items: baseline; font-size: .85rem; color: var(--ink-soft); }
  .shot figcaption .step-n {
    font-family: ui-monospace, 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; font-size: .72rem; color: #fff; background: var(--amber-600);
    border-radius: 999px; width: 20px; height: 20px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; margin-top: .1rem;
  }
  .shots-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1.2rem; }
  @media (max-width: 640px) { .shots-row { grid-template-columns: 1fr; } }

  /* ---------- Offline spotlight ---------- */
  .spotlight {
    margin-block: 3.5rem;
    border-radius: 28px;
    background: linear-gradient(160deg, var(--amber-900), #3a1505 78%);
    color: #fdf3e2;
    padding: 2.4rem 2.2rem;
    box-shadow: var(--shadow);
    position: relative;
    overflow: hidden;
  }
  :root[data-theme="dark"] .spotlight,
  :root:not([data-theme="light"]) .spotlight { color-scheme: dark; }
  .spotlight::after {
    content: ''; position: absolute; inset-inline-end: -60px; top: -60px;
    width: 220px; height: 220px; border-radius: 50%;
    background: radial-gradient(circle, rgb(253 230 138 / .35), transparent 70%);
  }
  .spotlight .chapter-num { color: #fbbf6a; }
  .spotlight h2 { font-size: 1.6rem; font-weight: 800; color: #fff8ec; }
  .spotlight p.lede { color: #fde9c8; font-size: 1.03rem; max-width: 66ch; margin-top: .7rem; }
  .spotlight .timeline { margin-top: 1.8rem; display: flex; flex-direction: column; gap: 1rem; position: relative; z-index: 1; }
  .spotlight .step {
    display: flex; gap: .9rem; align-items: flex-start;
    background: rgb(0 0 0 / .18);
    border: 1px solid rgb(253 230 138 / .25);
    border-radius: 14px;
    padding: .85rem 1.05rem;
  }
  .spotlight .step .n {
    font-family: ui-monospace, 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; font-size: .75rem;
    background: #fbbf6a; color: #3a1505; font-weight: 700;
    width: 22px; height: 22px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: .1rem;
  }
  .spotlight .step .t { font-weight: 700; font-size: .92rem; color: #fff8ec; }
  .spotlight .step .d { font-size: .84rem; color: #f3d9a8; margin-top: .2rem; line-height: 1.65; }
  .spotlight .shot {
    margin-top: 1.8rem; position: relative; z-index: 1;
    background: rgb(0 0 0 / .18); border-color: rgb(253 230 138 / .3);
  }
  .spotlight .shot figcaption { color: #f3d9a8; }
  .spotlight .shot figcaption .step-n { background: #fbbf6a; color: #3a1505; }

  /* ---------- Colophon ---------- */
  .colophon {
    margin-top: 3.5rem; padding-top: 2rem; border-top: 1px solid var(--border-soft);
    display: flex; flex-wrap: wrap; justify-content: space-between; gap: 1.5rem;
  }
  .colophon .brandmark svg { width: 22px; height: 22px; }
  .colophon .stack {
    font-family: ui-monospace, 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; font-size: .72rem; color: var(--ink-faint);
    direction: ltr; unicode-bidi: isolate; text-align: end;
  }
  .colophon .stack div:first-child { color: var(--ink-soft); margin-bottom: .3rem; }

  ul.plain { margin: .9rem 0 0; padding-inline-start: 1.2rem; color: var(--ink-soft); font-size: .92rem; }
  ul.plain li { margin-bottom: .35rem; }
</style>
</head>
<body>

<div class="page" dir="rtl" lang="fa">

  <div class="topbar">
    <div class="brandmark">
      <svg viewBox="0 0 100 100" aria-hidden="true">
        <defs>
          <linearGradient id="bg1" x1="20" y1="10" x2="80" y2="95" gradientUnits="userSpaceOnUse">
            <stop offset="0%" stop-color="#fde68a"/><stop offset="55%" stop-color="#f59e0b"/><stop offset="100%" stop-color="#d97706"/>
          </linearGradient>
          <linearGradient id="eg1" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" stop-color="#fde68a"/><stop offset="100%" stop-color="#f59e0b"/>
          </linearGradient>
        </defs>
        <circle cx="26" cy="40" r="10" fill="url(#eg1)"/><circle cx="74" cy="40" r="10" fill="url(#eg1)"/>
        <circle cx="50" cy="62" r="34" fill="url(#bg1)"/>
        <ellipse cx="21" cy="68" rx="6.5" ry="4.5" fill="#fff" opacity=".35"/><ellipse cx="79" cy="68" rx="6.5" ry="4.5" fill="#fff" opacity=".35"/>
        <circle cx="40" cy="61" r="4.2" fill="#1c1917"/><circle cx="60" cy="61" r="4.2" fill="#1c1917"/>
        <circle cx="41.6" cy="59.4" r="1.3" fill="#fff"/><circle cx="61.6" cy="59.4" r="1.3" fill="#fff"/>
        <path d="M42,73 Q50,80 58,73" stroke="#78350f" stroke-width="3" fill="none" stroke-linecap="round" opacity=".7"/>
        <path d="M16,41 C16,9 84,9 84,41 C84,45.5 74,41 62,39 C54,37.5 46,37.5 38,39 C26,41 16,45.5 16,41 Z" fill="#1c1917"/>
        <ellipse cx="50" cy="42" rx="29" ry="6.5" fill="#292524"/><circle cx="50" cy="12" r="3" fill="#44403c"/>
      </svg>
      <span class="word"><span style="color:var(--ink-soft)">Farsi</span><b>WP</b></span>
    </div>
    <span class="doc-kicker">Product Catalog — v1.0</span>
  </div>

  <!-- ============ COVER ============ -->
  <div class="cover">
    <svg class="mascot-big" viewBox="0 0 100 100" aria-hidden="true">
      <defs>
        <linearGradient id="bg2" x1="20" y1="10" x2="80" y2="95" gradientUnits="userSpaceOnUse">
          <stop offset="0%" stop-color="#fde68a"/><stop offset="55%" stop-color="#f59e0b"/><stop offset="100%" stop-color="#d97706"/>
        </linearGradient>
        <linearGradient id="eg2" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stop-color="#fde68a"/><stop offset="100%" stop-color="#f59e0b"/>
        </linearGradient>
      </defs>
      <circle cx="26" cy="40" r="10" fill="url(#eg2)"/><circle cx="74" cy="40" r="10" fill="url(#eg2)"/>
      <circle cx="50" cy="62" r="34" fill="url(#bg2)"/>
      <ellipse cx="21" cy="68" rx="6.5" ry="4.5" fill="#fff" opacity=".35"/><ellipse cx="79" cy="68" rx="6.5" ry="4.5" fill="#fff" opacity=".35"/>
      <circle cx="40" cy="61" r="4.2" fill="#1c1917"/><circle cx="60" cy="61" r="4.2" fill="#1c1917"/>
      <circle cx="41.6" cy="59.4" r="1.3" fill="#fff"/><circle cx="61.6" cy="59.4" r="1.3" fill="#fff"/>
      <path d="M42,73 Q50,80 58,73" stroke="#78350f" stroke-width="3" fill="none" stroke-linecap="round" opacity=".7"/>
      <path d="M16,41 C16,9 84,9 84,41 C84,45.5 74,41 62,39 C54,37.5 46,37.5 38,39 C26,41 16,45.5 16,41 Z" fill="#1c1917"/>
      <ellipse cx="50" cy="42" rx="29" ry="6.5" fill="#292524"/><circle cx="50" cy="12" r="3" fill="#44403c"/>
    </svg>

    <span class="eyebrow">راهنمای جامع سامانه</span>
    <h1>سامانه‌ی یکپارچه‌ی مدیریت FarsiWP</h1>
    <div class="tagline-en">Fully Automated Relationship &amp; System Intelligence, Word Pipeline</div>

    <p class="lede">
      یک پنل مدیریت واحد، فارسی و راست‌به‌چپ، برای اداره‌ی هم‌زمان اقامتگاه، فروش بلیط رویداد، روابط مشتری، قراردادها،
      فرم‌سازی و گردش‌کار، اسناد، ارتباطات داخلی و حضور‌و‌غیاب — با یک دستیار هوشمند که همه‌جای سامانه همراه کاربر است،
      و توانایی کار کردن حتی وقتی اینترنت قطع است.
    </p>

    <div class="meta-row">
      <span>ساخته‌شده روی <b>Laravel 13 + Filament v5.8</b></span>
      <span>تقویم <b>شمسی (جلالی)</b> در سراسر سامانه</span>
      <span>قابل نصب به‌عنوان <b>اپلیکیشن (PWA)</b></span>
      <span>پشتیبانی از <b>حالت آفلاین</b></span>
    </div>
  </div>

  <!-- ============ TOC ============ -->
  <nav class="toc" aria-label="فهرست فصل‌ها">
    <h2>فهرست فصل‌ها</h2>
    <ol>
      <li><a href="#ch-00">شروع کار: ورود و داشبورد</a></li>
      <li><a href="#ch-01">دستیار هوشمند مدیریتی</a></li>
      <li><a href="#ch-02">اقامتی و هتلداری</a></li>
      <li><a href="#ch-03">گیشه بلیط</a></li>
      <li><a href="#ch-04">CRM و مشتریان</a></li>
      <li><a href="#ch-05">فرم‌ساز و گردش‌کار</a></li>
      <li><a href="#ch-06">حقوقی و قرارداد</a></li>
      <li><a href="#ch-07">وظایف و پروژه‌ها</a></li>
      <li><a href="#ch-08">مدیریت اسناد</a></li>
      <li><a href="#ch-09">ارتباطات داخلی</a></li>
      <li><a href="#ch-10">حضور و غیاب</a></li>
      <li><a href="#ch-11">زیرساخت سازمانی و دسترسی‌ها</a></li>
      <li><a href="#ch-12">کار بدون اینترنت</a></li>
    </ol>
  </nav>

  <!-- ============ 00 — GETTING STARTED ============ -->
  <section class="chapter" id="ch-00">
    <div class="chapter-head">
      <div class="chapter-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/></svg>
      </div>
      <div>
        <span class="chapter-num">فصل ۰۰</span>
        <h2>شروع کار: ورود و داشبورد</h2>
      </div>
    </div>
    <div class="chapter-body">
      <p class="lede">
        با ایمیل و رمز عبور خود وارد شوید. صفحه‌ی نخست، داشبورد است: جمع‌بندی زنده‌ی وضعیت اقامتگاه، فروش، حضور
        پرسنل و کارهای در انتظار — همه در یک نگاه، پیش از رفتن به هر بخش دیگر.
      </p>
      <div class="howto">
        <div class="howto-label">مراحل استفاده</div>
        <div class="shots">
          <figure class="shot">
            <img src="/system-guide-assets/shots/00-login.png" alt="صفحه‌ی ورود سامانه">
            <figcaption><span class="step-n">۱</span> ایمیل و رمز عبور را در صفحه‌ی ورود وارد کنید.</figcaption>
          </figure>
          <figure class="shot">
            <img src="/system-guide-assets/shots/01-dashboard.png" alt="داشبورد سامانه">
            <figcaption><span class="step-n">۲</span> بعد از ورود، داشبورد باز می‌شود: درآمد، نرخ اشغال، حضور امروز، تسک‌های عقب‌افتاده و وضعیت رزروها.</figcaption>
          </figure>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ 01 — AI ASSISTANT ============ -->
  <section class="chapter" id="ch-01">
    <div class="chapter-head">
      <div class="chapter-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.8 4.6L18 9.5l-4.2 1.9L12 16l-1.8-4.6L6 9.5l4.2-1.9L12 3Z"/><path d="M19 15l.8 2 2 .8-2 .8-.8 2-.8-2-2-.8 2-.8.8-2Z"/></svg>
      </div>
      <div>
        <span class="chapter-num">فصل ۰۱</span>
        <h2>دستیار هوشمند مدیریتی</h2>
      </div>
    </div>
    <div class="chapter-body">
      <p class="lede">
        یک نماد پشمالوی شناور، همیشه گوشه‌ی صفحه — در هر بخشی از سامانه که باشید. دستیار هوشمند سؤالات درباره‌ی
        وضعیت سیستم را جواب می‌دهد، بدون این‌که مجبور باشید صفحه را عوض کنید.
      </p>
      <div class="feature-grid">
        <div class="feature-card">
          <div class="name">دستیار شناور همه‌جا</div>
          <div class="desc">روی هر صفحه از پنل در دسترس است؛ گفتگوی جدید، سؤال درباره‌ی وضعیت سامانه، بدون ترک صفحه‌ی فعلی.</div>
        </div>
        <div class="feature-card">
          <div class="name">صفحه‌ی دستیار هوشمند</div>
          <div class="desc">فضای اختصاصی برای گفتگوهای عمیق‌تر و پرس‌وجوهای پیچیده‌تر از هوش مصنوعی سامانه.</div>
        </div>
        <div class="feature-card">
          <div class="name">دستیار حقوقی (قرارداد)</div>
          <div class="desc">کمک هوش مصنوعی در تنظیم و بازبینی متن قراردادها، هم‌راستا با بخش حقوقی و قرارداد.</div>
        </div>
        <div class="feature-card">
          <div class="name">تنظیمات ارائه‌دهنده‌ی هوش مصنوعی</div>
          <div class="desc">انتخاب و پیکربندی سرویس هوش مصنوعی پشت‌صحنه، مستقل از بقیه‌ی بخش‌های سامانه.</div>
        </div>
      </div>
      <div class="howto">
        <div class="howto-label">مراحل استفاده</div>
        <div class="shots">
          <figure class="shot">
            <img src="/system-guide-assets/shots/02-ai-widget-floating.png" alt="نماد شناور دستیار هوشمند">
            <figcaption><span class="step-n">۱</span> نماد خرس گوشه‌ی پایین‌چپ روی هر صفحه دیده می‌شود؛ با کلیک روی آن گفتگو باز می‌شود.</figcaption>
          </figure>
          <figure class="shot">
            <img src="/system-guide-assets/shots/03-ai-assistant-page.png" alt="صفحه‌ی اختصاصی دستیار هوشمند">
            <figcaption><span class="step-n">۲</span> برای گفتگوهای طولانی‌تر، از منوی «دستیار هوشمند مدیریتی» وارد صفحه‌ی اختصاصی آن شوید.</figcaption>
          </figure>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ 02 — HOSPITALITY ============ -->
  <section class="chapter" id="ch-02">
    <div class="chapter-head">
      <div class="chapter-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 20V9m0 11h18M3 20l0-6a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v6M13 12V6a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v14"/><circle cx="6" cy="9" r="1.4" fill="currentColor" stroke="none"/></svg>
      </div>
      <div>
        <span class="chapter-num">فصل ۰۲</span>
        <h2>اقامتی و هتلداری</h2>
      </div>
    </div>
    <div class="chapter-body">
      <p class="lede">
        قلب عملیاتیِ پذیرش: از تعریف اتاق و نوع اتاق تا مشاهده‌ی وضعیت زنده‌ی هر واحد، ثبت رزرو، اتصال به کانال‌های
        فروش واسط (OTA) و قیمت‌گذاری فصلی — همه در یک‌جا.
      </p>
      <div class="feature-grid">
        <div class="feature-card"><div class="name">اتاق‌ها و انواع اتاق/ویلا</div><div class="desc">تعریف کامل موجودی اقامتگاه و دسته‌بندی هر واحد بر اساس نوع.</div></div>
        <div class="feature-card"><div class="name">رک اتاق — وضعیت زنده</div><div class="desc">نمای کارتی از همه‌ی واحدها: خالی، اشغال، نیازمند نظافت یا خارج از سرویس، به‌همراه مهمان مقیم و رزرو بعدی.</div></div>
        <div class="feature-card"><div class="name">رزروها</div><div class="desc">ثبت رزرو با بررسی خودکار تداخل تاریخ/اتاق، و دکمه‌های اختصاصی چک‌این و چک‌اوت سریع روی جدول.</div></div>
        <div class="feature-card"><div class="name">کانال‌های فروش و سهمیه‌بندی</div><div class="desc">اتصال به سایت‌های واسط، مدیریت سهمیه‌ی هر کانال و داشبورد یکپارچه‌ی مدیریت کانال.</div></div>
        <div class="feature-card"><div class="name">قیمت‌گذاری پویا</div><div class="desc">تعریف قواعد قیمتی فصلی/شرطی برای نرخ اتاق‌ها.</div></div>
      </div>
      <div class="callout">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round"><path d="M2 8.5a15 15 0 0 1 20 0M5.5 12a10 10 0 0 1 13 0M9 15.5a5 5 0 0 1 6 0"/><circle cx="12" cy="19" r="1" fill="currentColor" stroke="none"/></svg>
        <div>
          <span class="label">اولین قدم آفلاین‌سازی</span>
          <p>صفحه‌ی «رک اتاق» به‌عنوان اولین بخش سامانه، حتی بدون هیچ اتصال اینترنتی هم باز می‌شود و آخرین وضعیت
          ذخیره‌شده‌ی اتاق‌ها را نشان می‌دهد — درست مثل یک اپلیکیشن نصب‌شده. جزئیات کامل در فصل «کار بدون اینترنت».</p>
        </div>
      </div>
      <div class="howto">
        <div class="howto-label">مراحل استفاده</div>
        <div class="shots">
          <figure class="shot">
            <img src="/system-guide-assets/shots/04-room-rack.png" alt="رک اتاق - وضعیت زنده">
            <figcaption><span class="step-n">۱</span> رک اتاق: نمای کارتی از وضعیت زنده‌ی هر واحد و مهمان مقیم/رزرو بعدی آن.</figcaption>
          </figure>
          <figure class="shot">
            <img src="/system-guide-assets/shots/05-rooms-list.png" alt="فهرست اتاق‌ها">
            <figcaption><span class="step-n">۲</span> فهرست اتاق‌ها: مدیریت موجودی اقامتگاه از منوی «اتاق‌ها و ویلاها».</figcaption>
          </figure>
          <figure class="shot">
            <img src="/system-guide-assets/shots/06-room-create.png" alt="فرم ایجاد اتاق">
            <figcaption><span class="step-n">۳</span> دکمه‌ی «ایجاد»: تعریف اتاق جدید با نوع، دپارتمان و وضعیت اولیه.</figcaption>
          </figure>
          <figure class="shot">
            <img src="/system-guide-assets/shots/07-bookings-list.png" alt="فهرست رزروها">
            <figcaption><span class="step-n">۴</span> فهرست رزروها، با دکمه‌های اختصاصی چک‌این/چک‌اوت روی هر ردیف.</figcaption>
          </figure>
          <figure class="shot">
            <img src="/system-guide-assets/shots/08-booking-create.png" alt="فرم ایجاد رزرو">
            <figcaption><span class="step-n">۵</span> ثبت رزرو جدید: انتخاب اتاق و مهمان، تاریخ ورود/خروج، وضعیت و منبع رزرو.</figcaption>
          </figure>
          <figure class="shot">
            <img src="/system-guide-assets/shots/09-channel-manager.png" alt="داشبورد مدیریت کانال‌های فروش">
            <figcaption><span class="step-n">۶</span> داشبورد کانال‌های فروش: نمای یکپارچه از سهمیه و وضعیت هر کانال (OTA).</figcaption>
          </figure>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ 03 — TICKETING ============ -->
  <section class="chapter" id="ch-03">
    <div class="chapter-head">
      <div class="chapter-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4V8Z"/><path d="M10 6v12" stroke-dasharray="2 3"/></svg>
      </div>
      <div>
        <span class="chapter-num">فصل ۰۳</span>
        <h2>گیشه بلیط</h2>
      </div>
    </div>
    <div class="chapter-body">
      <p class="lede">
        فروش بلیط رویداد از صفر تا صد: تعریف محصول و انواع بلیط، کدهای تخفیف، دروازه‌های ورودی، صندوق فروش و
        اسکنر ورودیِ درگاه — با پشتیبانی کامل از حالت آفلاین برای لحظه‌ی ورود مهمانان.
      </p>
      <div class="feature-grid">
        <div class="feature-card"><div class="name">محصولات و انواع بلیط</div><div class="desc">تعریف رویداد، نوع بلیط و قیمت‌گذاری هر نوع.</div></div>
        <div class="feature-card"><div class="name">کدهای تخفیف</div><div class="desc">ساخت و مدیریت کدهای تخفیف قابل اعمال هنگام فروش.</div></div>
        <div class="feature-card"><div class="name">دروازه‌های ورودی</div><div class="desc">تعریف نقاط ورود و قواعد اعتبارسنجی بلیط برای هرکدام.</div></div>
        <div class="feature-card"><div class="name">صندوق فروش (Cashier)</div><div class="desc">صدور بلیط، اعمال تخفیف یا بلیط رایگان، و چاپ بلیط از همان صفحه.</div></div>
        <div class="feature-card"><div class="name">اسکن با دوربین</div><div class="desc">باز شدن خودکار دوربین گوشی/تبلت برای اسکن زنده‌ی QR بلیط، در کنار ورودی دستی و اسکنر سخت‌افزاری.</div></div>
      </div>
      <div class="callout">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round"><path d="M12 3v3M12 18v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M3 12h3M18 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/><circle cx="12" cy="12" r="4"/></svg>
        <div>
          <span class="label">اسکنر ورودی — کاملاً آفلاین</span>
          <p>اسکنر دروازه پیشرفته‌ترین حالت آفلاین سامانه را دارد: بدون هیچ اتصالی، بلیط‌ها را همان‌جا و دقیقاً با
          همان قواعد سرور اعتبارسنجی می‌کند، و به‌محض وصل‌شدن اینترنت، تمام اسکن‌های ثبت‌شده را با سرور همگام می‌کند.</p>
        </div>
      </div>
      <div class="howto">
        <div class="howto-label">مراحل استفاده</div>
        <div class="shots">
          <figure class="shot">
            <img src="/system-guide-assets/shots/11-cashier.png" alt="صندوق فروش بلیط">
            <figcaption><span class="step-n">۱</span> صندوق فروش: انتخاب محصول و نوع بلیط، ثبت اطلاعات مشتری، اعمال کد تخفیف یا بلیط رایگان، و پرداخت.</figcaption>
          </figure>
          <figure class="shot">
            <img src="/system-guide-assets/shots/10-gate-scanner.png" alt="اسکنر ورودی گیت">
            <figcaption><span class="step-n">۲</span> اسکنر گیت: انتخاب دروازه‌ی ورودی و اسکن QR بلیط، با نشانگر وضعیت اتصال (آنلاین/آفلاین).</figcaption>
          </figure>
          <figure class="shot">
            <img src="/system-guide-assets/shots/33-gate-scanner-camera.png" alt="اسکن بلیط با دوربین">
            <figcaption><span class="step-n">۳</span> پس از انتخاب دروازه، دوربین گوشی یا تبلت به‌طور خودکار باز می‌شود و QR بلیط را زنده اسکن می‌کند؛ ورودی متنی زیر آن برای اسکنر سخت‌افزاری یا وارد کردن دستی کد همچنان در دسترس است.</figcaption>
          </figure>
          <figure class="shot">
            <img src="/system-guide-assets/shots/12-products-list.png" alt="فهرست محصولات و رویدادها">
            <figcaption><span class="step-n">۴</span> فهرست محصولات: تعریف رویداد و انواع بلیط پیش از فروش.</figcaption>
          </figure>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ 04 — CRM ============ -->
  <section class="chapter" id="ch-04">
    <div class="chapter-head">
      <div class="chapter-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M16 4.5c1.8.3 3 1.9 3 3.7s-1.2 3.4-3 3.7M20.5 20a5 5 0 0 0-4-5.4"/></svg>
      </div>
      <div>
        <span class="chapter-num">فصل ۰۴</span>
        <h2>CRM و مشتریان</h2>
      </div>
    </div>
    <div class="chapter-body">
      <p class="lede">
        از اولین تماس تا معامله‌ی بسته‌شده: پیگیری مشتری، مسیر فروش، و صدای مهمانان بعد از اقامت — همه در یک قیف واحد.
      </p>
      <div class="feature-grid">
        <div class="feature-card"><div class="name">معاملات (Deals)</div><div class="desc">ثبت و پیگیری فرصت‌های فروش تا مرحله‌ی نهایی.</div></div>
        <div class="feature-card"><div class="name">قیف فروش</div><div class="desc">نمای تصویری از مسیر معاملات در هر مرحله.</div></div>
        <div class="feature-card"><div class="name">مهمانان</div><div class="desc">پروفایل کامل هر مهمان، مستقل از رزروهای تکراری.</div></div>
        <div class="feature-card"><div class="name">بازخورد و تیکت مهمانان</div><div class="desc">ثبت نظرات مهمانان و پیگیری درخواست‌های پشتیبانی آن‌ها.</div></div>
      </div>
      <div class="howto">
        <div class="howto-label">مراحل استفاده</div>
        <div class="shots">
          <figure class="shot">
            <img src="/system-guide-assets/shots/13-deals-list.png" alt="فهرست معاملات">
            <figcaption><span class="step-n">۱</span> فهرست معاملات: پیگیری هر فرصت فروش از منوی «معاملات فروش».</figcaption>
          </figure>
          <figure class="shot">
            <img src="/system-guide-assets/shots/14-sales-pipeline.png" alt="قیف فروش">
            <figcaption><span class="step-n">۲</span> قیف فروش: نمای تصویری از توزیع معاملات باز در هر مرحله، به‌همراه شاخص‌های کلیدی.</figcaption>
          </figure>
          <figure class="shot">
            <img src="/system-guide-assets/shots/15-guests-list.png" alt="فهرست مهمانان">
            <figcaption><span class="step-n">۳</span> فهرست مهمانان: پروفایل هر مهمان، مستقل از تاریخچه‌ی رزروهایش.</figcaption>
          </figure>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ 05 — FORM BUILDER ============ -->
  <section class="chapter" id="ch-05">
    <div class="chapter-head">
      <div class="chapter-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="4" width="17" height="16" rx="2.5"/><path d="M3.5 9.5h17M8.5 9.5V20"/></svg>
      </div>
      <div>
        <span class="chapter-num">فصل ۰۵</span>
        <h2>فرم‌ساز و گردش‌کار</h2>
      </div>
    </div>
    <div class="chapter-body">
      <p class="lede">
        فرم‌های اختصاصی سازمان را خودتان بسازید، به یک گردش‌کار چندمرحله‌ای وصل کنید، و نتیجه‌ی هر بار پرشدنش را
        در قالب گزارش دوره‌ای ببینید.
      </p>
      <div class="feature-grid">
        <div class="feature-card"><div class="name">قالب‌های فرم</div><div class="desc">طراحی فرم فیلد‌به‌فیلد: متن، انتخابی، جدول و قواعد اعتبارسنجی هرکدام.</div></div>
        <div class="feature-card"><div class="name">تعریف گردش‌کار</div><div class="desc">مسیر چندمرحله‌ای پردازش/تأیید برای هر فرم.</div></div>
        <div class="feature-card"><div class="name">ثبت‌های فرم</div><div class="desc">آرشیو کامل پاسخ‌های ثبت‌شده، مرتبط با مرحله‌ی گردش‌کار خودشان.</div></div>
        <div class="feature-card"><div class="name">گزارش دوره‌ای فرم‌ها</div><div class="desc">نمای جمع‌بندی‌شده از ثبت‌ها در بازه‌های زمانی مشخص.</div></div>
      </div>
      <div class="callout">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round"><path d="M12 5v6l4 2"/><circle cx="12" cy="12" r="9"/></svg>
        <div>
          <span class="label">ثبت اطلاعات، حتی وقتی اینترنت قطعه</span>
          <p>اگر لحظه‌ی ثبت یک فرم، اتصال قطع شود، سامانه ثبت را گم نمی‌کند: آن را ذخیره می‌کند و به‌محض وصل‌شدن
          دوباره، به‌طور خودکار ارسالش می‌کند — بدون این‌که دوباره وارد شود.</p>
        </div>
      </div>
      <div class="howto">
        <div class="howto-label">مراحل استفاده</div>
        <div class="shots">
          <figure class="shot">
            <img src="/system-guide-assets/shots/16-form-templates-list.png" alt="فهرست قالب‌های فرم">
            <figcaption><span class="step-n">۱</span> فهرست قالب‌های فرم؛ از اینجا فرم جدید بسازید یا فرم موجود را ویرایش کنید.</figcaption>
          </figure>
          <figure class="shot">
            <img src="/system-guide-assets/shots/17-form-template-fields.png" alt="ویرایش قالب فرم">
            <figcaption><span class="step-n">۲</span> ویرایش قالب: عنوان، توضیحات، اتصال به گردش‌کار تأیید، دپارتمان مسئول و متن سند.</figcaption>
          </figure>
          <figure class="shot">
            <img src="/system-guide-assets/shots/18-form-submissions-list.png" alt="فهرست ثبت‌های فرم">
            <figcaption><span class="step-n">۳</span> ثبت‌های فرم: آرشیو پاسخ‌های دریافتی از هر قالب.</figcaption>
          </figure>
          <figure class="shot">
            <img src="/system-guide-assets/shots/19-workflow-definitions.png" alt="فهرست گردش‌کارهای تأیید">
            <figcaption><span class="step-n">۴</span> تعریف گردش‌کار تأیید: مسیر چندمرحله‌ای پردازش هر فرم.</figcaption>
          </figure>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ 06 — LEGAL ============ -->
  <section class="chapter" id="ch-06">
    <div class="chapter-head">
      <div class="chapter-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v18M5 7h14M5 7l-3 6a3 3 0 0 0 6 0L5 7ZM19 7l-3 6a3 3 0 0 0 6 0l-3-6Z"/></svg>
      </div>
      <div>
        <span class="chapter-num">فصل ۰۶</span>
        <h2>حقوقی و قرارداد</h2>
      </div>
    </div>
    <div class="chapter-body">
      <p class="lede">آرشیو منظم قراردادها، دسته‌بندی‌شده و آماده برای بازبینی، با کمک دستیار هوشمند حقوقی در فصل نگارش.</p>
      <div class="feature-grid">
        <div class="feature-card"><div class="name">قراردادها</div><div class="desc">ثبت کامل چرخه‌ی عمر هر قرارداد.</div></div>
        <div class="feature-card"><div class="name">دسته‌بندی قراردادها</div><div class="desc">سازمان‌دهی قراردادها بر اساس نوع و کاربرد.</div></div>
      </div>
      <div class="howto">
        <div class="howto-label">مراحل استفاده</div>
        <div class="shots-row">
          <figure class="shot">
            <img src="/system-guide-assets/shots/20-contracts-list.png" alt="آرشیو قراردادها">
            <figcaption><span class="step-n">۱</span> آرشیو قراردادها، قابل جست‌وجو بر اساس عنوان، دسته و طرف مقابل.</figcaption>
          </figure>
          <figure class="shot">
            <img src="/system-guide-assets/shots/21-contract-assistant.png" alt="دستیار حقوقی قرارداد">
            <figcaption><span class="step-n">۲</span> دستیار حقوقی: کمک هوش مصنوعی هنگام نگارش و بازبینی متن قرارداد.</figcaption>
          </figure>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ 07 — TASKS ============ -->
  <section class="chapter" id="ch-07">
    <div class="chapter-head">
      <div class="chapter-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l2 2 4-4"/><rect x="4" y="4" width="16" height="16" rx="3"/></svg>
      </div>
      <div>
        <span class="chapter-num">فصل ۰۷</span>
        <h2>وظایف و پروژه‌ها</h2>
      </div>
    </div>
    <div class="chapter-body">
      <p class="lede">پروژه‌ها را تعریف کنید، زیرشان وظیفه بسازید، و برای کارهای تکراری از قالب چک‌لیست آماده استفاده کنید.</p>
      <div class="feature-grid">
        <div class="feature-card"><div class="name">پروژه‌ها</div><div class="desc">سطح بالای سازمان‌دهی کار تیمی.</div></div>
        <div class="feature-card"><div class="name">وظایف</div><div class="desc">آیتم‌های کاری زیر هر پروژه.</div></div>
        <div class="feature-card"><div class="name">قالب‌های چک‌لیست تکرارشونده</div><div class="desc">الگوی آماده برای فرایندهای تکراری، بدون بازتعریف هر بار.</div></div>
      </div>
      <div class="howto">
        <div class="howto-label">مراحل استفاده</div>
        <div class="shots-row">
          <figure class="shot">
            <img src="/system-guide-assets/shots/22-projects-list.png" alt="فهرست پروژه‌ها">
            <figcaption><span class="step-n">۱</span> فهرست پروژه‌ها: دپارتمان، متولی اجرایی، بودجه، پیشرفت و وضعیت هر پروژه.</figcaption>
          </figure>
          <figure class="shot">
            <img src="/system-guide-assets/shots/23-tasks-list.png" alt="فهرست وظایف">
            <figcaption><span class="step-n">۲</span> فهرست وظایف: آیتم‌های کاری زیرمجموعه‌ی هر پروژه.</figcaption>
          </figure>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ 08 — DOCUMENTS ============ -->
  <section class="chapter" id="ch-08">
    <div class="chapter-head">
      <div class="chapter-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7a2 2 0 0 1 2-2h4l2 2h6a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7Z"/></svg>
      </div>
      <div>
        <span class="chapter-num">فصل ۰۸</span>
        <h2>مدیریت اسناد</h2>
      </div>
    </div>
    <div class="chapter-body">
      <p class="lede">آرشیو دیجیتال سازمان، در پوشه‌های سلسله‌مراتبی و با یک مرورگر آرشیو اختصاصی برای پیدا کردن سریع هر سند.</p>
      <div class="feature-grid">
        <div class="feature-card"><div class="name">اسناد</div><div class="desc">بارگذاری و نگهداری فایل‌های سازمانی.</div></div>
        <div class="feature-card"><div class="name">پوشه‌های سند</div><div class="desc">سازمان‌دهی سلسله‌مراتبی آرشیو.</div></div>
        <div class="feature-card"><div class="name">مرورگر آرشیو اسناد</div><div class="desc">نمای اختصاصی برای جست‌وجو و مرور کل آرشیو.</div></div>
      </div>
      <div class="howto">
        <div class="howto-label">مراحل استفاده</div>
        <div class="shots-row">
          <figure class="shot">
            <img src="/system-guide-assets/shots/24-documents-list.png" alt="فهرست اسناد">
            <figcaption><span class="step-n">۱</span> فهرست اسناد، هر سند وابسته به پوشه‌ی مربوط به خودش.</figcaption>
          </figure>
          <figure class="shot">
            <img src="/system-guide-assets/shots/25-document-archive.png" alt="مرورگر آرشیو اسناد">
            <figcaption><span class="step-n">۲</span> مرورگر آرشیو: مرور بصری کل ساختار پوشه‌ها و اسناد.</figcaption>
          </figure>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ 09 — INTERNAL COMMS ============ -->
  <section class="chapter" id="ch-09">
    <div class="chapter-head">
      <div class="chapter-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.4 8.4 0 0 1-8.9 8.4L4 21l1.3-3.9A8.4 8.4 0 1 1 21 11.5Z"/></svg>
      </div>
      <div>
        <span class="chapter-num">فصل ۰۹</span>
        <h2>ارتباطات داخلی</h2>
      </div>
    </div>
    <div class="chapter-body">
      <p class="lede">پیام‌رسان داخلی تیم، با پیوست فایل و ضبط پیام صوتی مستقیم از داخل گفتگو، به‌همراه رویدادهای سازمانی.</p>
      <div class="feature-grid">
        <div class="feature-card"><div class="name">پیام‌رسان</div><div class="desc">گفتگوی مستقیم بین اعضای تیم، با پیوست فایل و تصویر.</div></div>
        <div class="feature-card"><div class="name">پیام صوتی</div><div class="desc">ضبط، پیش‌نمایش قبل از ارسال و پخش پیام‌های صوتی، با نمایش زمان ضبط.</div></div>
        <div class="feature-card"><div class="name">رویدادها</div><div class="desc">ثبت و اطلاع‌رسانی رویدادهای داخلی سازمان.</div></div>
      </div>
      <div class="howto">
        <div class="howto-label">مراحل استفاده</div>
        <div class="shots">
          <figure class="shot">
            <img src="/system-guide-assets/shots/26-messenger.png" alt="پیام‌رسان داخلی با پیام صوتی">
            <figcaption><span class="step-n">۱</span> گفتگو با هر همکار؛ برای ارسال پیام صوتی روی آیکون میکروفون بزنید و برای پیوست فایل از گیره‌ی کنار آن استفاده کنید — همان‌طور که در این گفتگوی واقعی، هم فایل و هم پیام صوتی پخش‌شدنی دیده می‌شود.</figcaption>
          </figure>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ 10 — ATTENDANCE ============ -->
  <section class="chapter" id="ch-10">
    <div class="chapter-head">
      <div class="chapter-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg>
      </div>
      <div>
        <span class="chapter-num">فصل ۱۰</span>
        <h2>حضور و غیاب</h2>
      </div>
    </div>
    <div class="chapter-body">
      <p class="lede">شیفت‌های کاری را تعریف کنید و ورود‌وخروج پرسنل را، هم از دید مدیر و هم از دید خودشان، رصد کنید.</p>
      <div class="feature-grid">
        <div class="feature-card"><div class="name">سوابق حضور و غیاب</div><div class="desc">نمای مدیریتی از حضور همه‌ی پرسنل.</div></div>
        <div class="feature-card"><div class="name">حضور من</div><div class="desc">صفحه‌ی شخصی هر کاربر برای ثبت و مرور حضور خودش.</div></div>
        <div class="feature-card"><div class="name">شیفت‌های کاری</div><div class="desc">تعریف الگوهای شیفت برای برنامه‌ریزی پرسنل.</div></div>
      </div>
      <div class="howto">
        <div class="howto-label">مراحل استفاده</div>
        <div class="shots-row">
          <figure class="shot">
            <img src="/system-guide-assets/shots/27-attendance-records.png" alt="سوابق حضور و غیاب">
            <figcaption><span class="step-n">۱</span> نمای مدیریتی: سوابق حضور همه‌ی پرسنل در یک جدول.</figcaption>
          </figure>
          <figure class="shot">
            <img src="/system-guide-assets/shots/28-my-attendance.png" alt="حضور و غیاب من">
            <figcaption><span class="step-n">۲</span> صفحه‌ی «حضور من»: هر کاربر ورود/خروج و سوابق خودش را می‌بیند.</figcaption>
          </figure>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ 11 — ORG INFRA ============ -->
  <section class="chapter" id="ch-11">
    <div class="chapter-head">
      <div class="chapter-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="18" rx="1.5"/><path d="M8 8h1M8 12h1M8 16h1M12 8h4M12 12h4M12 16h2"/></svg>
      </div>
      <div>
        <span class="chapter-num">فصل ۱۱</span>
        <h2>زیرساخت سازمانی و دسترسی‌ها</h2>
      </div>
    </div>
    <div class="chapter-body">
      <p class="lede">
        اسکلت پشت‌صحنه‌ی سامانه: شرکت‌ها، دپارتمان‌ها، کاربران و — مهم‌تر از همه — یک سیستم نقش و دسترسی کاملاً
        پیکربندی‌پذیر که مشخص می‌کند هر نقش دقیقاً به کدام بخش‌ها دسترسی دارد.
      </p>
      <div class="feature-grid">
        <div class="feature-card"><div class="name">شرکت‌ها و دپارتمان‌ها</div><div class="desc">ساختار سازمانی و طرف‌های تجاری.</div></div>
        <div class="feature-card"><div class="name">کاربران و نقش‌ها</div><div class="desc">مدیریت دسترسی مبتنی بر نقش (RBAC)، قابل تنظیم برای هر ماژول به‌طور جداگانه.</div></div>
        <div class="feature-card"><div class="name">برچسب‌ها</div><div class="desc">برچسب‌گذاری آزاد، مشترک بین بخش‌های مختلف سامانه.</div></div>
        <div class="feature-card"><div class="name">تنظیمات پیامک</div><div class="desc">پیکربندی سرویس ارسال پیامک سامانه.</div></div>
      </div>
      <div class="callout">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round"><path d="M12 2 4 6v6c0 5 3.5 8.4 8 10 4.5-1.6 8-5 8-10V6l-8-4Z"/><path d="M9 12l2 2 4-4"/></svg>
        <div>
          <span class="label">ثبت رویداد و ردیابی تغییرات</span>
          <p>هر تغییر مهم در سامانه با مشخصات کاربر و زمان دقیق ثبت می‌شود؛ یک ردپای قابل پیگیری برای بازبینی بعدی.</p>
        </div>
      </div>
      <div class="howto">
        <div class="howto-label">مراحل استفاده</div>
        <div class="shots-row">
          <figure class="shot">
            <img src="/system-guide-assets/shots/29-users-list.png" alt="فهرست کاربران">
            <figcaption><span class="step-n">۱</span> فهرست کاربران؛ هر کاربر یک یا چند نقش می‌گیرد.</figcaption>
          </figure>
          <figure class="shot">
            <img src="/system-guide-assets/shots/30-roles-list.png" alt="فهرست نقش‌ها و سطوح دسترسی">
            <figcaption><span class="step-n">۲</span> نقش‌ها و سطوح دسترسی: مشخص کردن دقیق دسترسی هر نقش به هر بخش.</figcaption>
          </figure>
          <figure class="shot">
            <img src="/system-guide-assets/shots/31-companies-list.png" alt="فهرست شرکت‌ها">
            <figcaption><span class="step-n">۳</span> شرکت‌های هلدینگ: طرف‌های تجاری و سازمانی مرتبط.</figcaption>
          </figure>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ 12 — OFFLINE SPOTLIGHT ============ -->
  <section class="chapter spotlight" id="ch-12" style="border-top:none;">
    <div class="chapter-head" style="margin-bottom:.4rem;">
      <div>
        <span class="chapter-num">فصل ۱۲ — ویژگی متمایز</span>
        <h2>کار بدون اینترنت</h2>
      </div>
    </div>
    <p class="lede">
      این سامانه تحت وب است، اما برای لحظه‌های حساس — قطعی موقت اینترنت روی میز پذیرش، یا نبود آنتن کنار دروازه‌ی
      ورودی رویداد — طوری طراحی شده که کار متوقف نشود و داده هم گم نشود.
    </p>
    <div class="timeline">
      <div class="step">
        <span class="n">۱</span>
        <div>
          <div class="t">هشدار غیرمزاحم آفلاین‌بودن</div>
          <div class="d">نواری کوچک در پایین صفحه، در سراسر پنل، نشان می‌دهد اتصال قطع شده — بدون قطع‌کردن کار کاربر.</div>
        </div>
      </div>
      <div class="step">
        <span class="n">۲</span>
        <div>
          <div class="t">هشدار پیش از ترک صفحه</div>
          <div class="d">اگر کاربر بخواهد وسط قطعی، تب را ببندد یا صفحه را عوض کند، مرورگر برایش تأیید می‌گیرد تا اطلاعات وارد‌شده از بین نرود.</div>
        </div>
      </div>
      <div class="step">
        <span class="n">۳</span>
        <div>
          <div class="t">ثبت خودکار بعد از وصل‌شدن</div>
          <div class="d">فرم‌های ثبت‌شده حین قطعی (رزرو، معامله، ثبت فرم، اتاق) صف می‌شوند و به‌محض وصل‌شدن اینترنت، خودکار و بدون ثبت تکراری ارسال می‌شوند.</div>
        </div>
      </div>
      <div class="step">
        <span class="n">۴</span>
        <div>
          <div class="t">اسکن ورودی، کاملاً بدون سرور</div>
          <div class="d">اسکنر دروازه‌ی گیشه‌ی بلیط، بدون هیچ اتصالی، بلیط اعتبارسنجی می‌کند و بعداً با سرور همگام می‌شود.</div>
        </div>
      </div>
      <div class="step">
        <span class="n">۵</span>
        <div>
          <div class="t">صفحه‌ای که مثل اپلیکیشن بالا می‌آید</div>
          <div class="d">رک اتاق — اولین نمونه از این سطح — حتی با نبود کامل اینترنت هم باز می‌شود و آخرین وضعیت ذخیره‌شده را نشان می‌دهد.</div>
        </div>
      </div>
    </div>
    <figure class="shot">
      <img src="/system-guide-assets/shots/32-room-rack-offline.png" alt="رک اتاق در حالت کاملاً آفلاین">
      <figcaption><span class="step-n">✓</span> عکس واقعی: اینترنت کاملاً قطع است، و رک اتاق همچنان با آخرین وضعیت ذخیره‌شده باز می‌شود؛ نوار زرد بالای صفحه زمان آخرین به‌روزرسانی را نشان می‌دهد و نوار پایین صفحه یادآوری می‌کند که کارتون رو ادامه بدید.</figcaption>
    </figure>
  </section>

  <!-- ============ COLOPHON ============ -->
  <div class="colophon">
    <div class="brandmark">
      <svg viewBox="0 0 100 100" aria-hidden="true">
        <defs><linearGradient id="bg3" x1="20" y1="10" x2="80" y2="95" gradientUnits="userSpaceOnUse"><stop offset="0%" stop-color="#fde68a"/><stop offset="55%" stop-color="#f59e0b"/><stop offset="100%" stop-color="#d97706"/></linearGradient><linearGradient id="eg3" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#fde68a"/><stop offset="100%" stop-color="#f59e0b"/></linearGradient></defs>
        <circle cx="26" cy="40" r="10" fill="url(#eg3)"/><circle cx="74" cy="40" r="10" fill="url(#eg3)"/>
        <circle cx="50" cy="62" r="34" fill="url(#bg3)"/>
        <ellipse cx="21" cy="68" rx="6.5" ry="4.5" fill="#fff" opacity=".35"/><ellipse cx="79" cy="68" rx="6.5" ry="4.5" fill="#fff" opacity=".35"/>
        <circle cx="40" cy="61" r="4.2" fill="#1c1917"/><circle cx="60" cy="61" r="4.2" fill="#1c1917"/>
        <circle cx="41.6" cy="59.4" r="1.3" fill="#fff"/><circle cx="61.6" cy="59.4" r="1.3" fill="#fff"/>
        <path d="M42,73 Q50,80 58,73" stroke="#78350f" stroke-width="3" fill="none" stroke-linecap="round" opacity=".7"/>
        <path d="M16,41 C16,9 84,9 84,41 C84,45.5 74,41 62,39 C54,37.5 46,37.5 38,39 C26,41 16,45.5 16,41 Z" fill="#1c1917"/>
        <ellipse cx="50" cy="42" rx="29" ry="6.5" fill="#292524"/><circle cx="50" cy="12" r="3" fill="#44403c"/>
      </svg>
      <span class="word"><span style="color:var(--ink-soft)">Farsi</span><b>WP</b></span>
    </div>
    <div class="stack">
      <div>Laravel 13 · Filament v5.8 · PHP 8.4</div>
      <div>Persian-first · RTL · Jalali calendar · Installable PWA</div>
    </div>
  </div>

</div>
</body>
</html>
