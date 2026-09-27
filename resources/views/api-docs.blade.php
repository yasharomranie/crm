<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>مستندات API سامانه FarsiWP</title>
<style>
  /* Self-hosted, not a Google Fonts CDN link - this app is hosted for
     users in Iran, where third-party CDNs can be slow or filtered (see
     .ai/rules/general.md). Same variable-weight file this app's own
     Vite build already produces, just copied under a stable name so a
     later `npm run build` re-hashing that file doesn't break this page -
     same font file system-guide.blade.php already self-hosts this way. */
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
    --get: #0369a1;
    --get-bg: #e0f2fe;
    --post: #15803d;
    --post-bg: #dcfce7;
    --delete: #b91c1c;
    --delete-bg: #fee2e2;
    --code-bg: #211c16;
    --code-ink: #f3ece0;
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
      --get: #7dd3fc;
      --get-bg: #0c2a3a;
      --post: #86efac;
      --post-bg: #0f2a19;
      --delete: #fca5a5;
      --delete-bg: #3a1414;
      --code-bg: #100d0a;
      --code-ink: #f3ece0;
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
    --get: #7dd3fc;
    --get-bg: #0c2a3a;
    --post: #86efac;
    --post-bg: #0f2a19;
    --delete: #fca5a5;
    --delete-bg: #3a1414;
    --code-bg: #100d0a;
    --code-ink: #f3ece0;
    --shadow: 0 18px 40px -18px rgb(0 0 0 / 0.55);
    --shadow-sm: 0 8px 20px -12px rgb(0 0 0 / 0.5);
    color-scheme: dark;
  }

  body { background: var(--paper); color: var(--ink); }

  .page {
    font-family: 'Vazirmatn', Tahoma, Arial, sans-serif;
    direction: rtl;
    max-width: 980px;
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
    display: flex; align-items: center; justify-content: space-between; gap: .75rem;
    padding-block-end: 2.25rem; border-bottom: 1px solid var(--border-soft); margin-bottom: 2.75rem;
  }
  .brandmark { display: flex; align-items: center; gap: .6rem; }
  .brandmark svg { width: 30px; height: 30px; display: block; }
  .brandmark .word { font-weight: 800; font-size: 1.05rem; line-height: 1; direction: ltr; unicode-bidi: isolate; }
  .brandmark .word b { color: var(--amber-600); font-weight: 800; }
  .topbar .doc-kicker {
    font-family: ui-monospace, 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
    font-size: .68rem; letter-spacing: .06em; color: var(--ink-faint);
    direction: ltr; unicode-bidi: isolate; text-transform: uppercase;
  }

  /* ---------- Cover ---------- */
  .cover { padding-block: 1rem 3rem; }
  .cover .eyebrow {
    display: inline-flex; align-items: center; gap: .45rem;
    font-family: ui-monospace, 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
    font-size: .72rem; letter-spacing: .05em; color: var(--amber-600);
    background: var(--paper-2); border: 1px solid var(--border); border-radius: 999px;
    padding: .3rem .8rem; margin-bottom: 1.1rem;
  }
  .cover .eyebrow::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: var(--amber-500); }
  .cover h1 { font-size: clamp(2rem, 5vw, 2.8rem); font-weight: 800; letter-spacing: -.01em; }
  .cover p.lede { max-width: 68ch; color: var(--ink-soft); font-size: 1.05rem; margin-top: 1.4rem; }
  .cover .meta-row { display: flex; flex-wrap: wrap; gap: .5rem 1.4rem; margin-top: 1.6rem; font-size: .82rem; color: var(--ink-faint); }
  .cover .meta-row span b { color: var(--ink-soft); font-weight: 600; }

  /* ---------- TOC ---------- */
  .toc {
    background: var(--paper-2); border: 1px solid var(--border); border-radius: 20px;
    padding: 1.6rem 1.7rem; margin-block: 3rem;
  }
  .toc h2 { font-size: .82rem; letter-spacing: .04em; color: var(--ink-faint); text-transform: uppercase; font-family: ui-monospace, 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; margin-bottom: 1rem; }
  .toc ol { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: 1fr 1fr; gap: .35rem 1.5rem; counter-reset: toc; }
  .toc li { counter-increment: toc; }
  .toc a { color: var(--ink); text-decoration: none; font-size: .92rem; display: flex; gap: .5rem; }
  .toc a::before { content: counter(toc, decimal-leading-zero); font-family: ui-monospace, monospace; color: var(--amber-600); font-size: .8rem; }
  .toc a:hover { color: var(--amber-600); }

  /* ---------- Chapters ---------- */
  section.chapter { padding-block: 2.6rem; border-top: 1px solid var(--border-soft); scroll-margin-top: 1.5rem; }
  section.chapter:first-of-type { border-top: none; }
  .chapter-head { display: flex; align-items: flex-start; gap: 1.1rem; margin-bottom: 1.3rem; }
  .chapter-icon {
    flex-shrink: 0; width: 46px; height: 46px; border-radius: 14px;
    background: linear-gradient(160deg, var(--amber-100), var(--amber-500));
    display: flex; align-items: center; justify-content: center;
  }
  .chapter-icon svg { width: 24px; height: 24px; stroke: var(--amber-ink); }
  .chapter-num { font-family: ui-monospace, monospace; font-size: .74rem; letter-spacing: .05em; color: var(--amber-600); text-transform: uppercase; }
  .chapter-head h2 { font-size: 1.4rem; font-weight: 800; margin-top: .15rem; }
  .chapter-body p { color: var(--ink-soft); max-width: 72ch; }
  .chapter-body p.lede { font-size: 1rem; color: var(--ink); margin-bottom: 1.1rem; }
  .chapter-body h3 { font-size: 1.02rem; font-weight: 700; margin-top: 2rem; margin-bottom: .6rem; }
  .chapter-body ol.steps { margin: 0; padding-inline-start: 1.4rem; display: flex; flex-direction: column; gap: .7rem; }
  .chapter-body ol.steps li { color: var(--ink-soft); font-size: .94rem; }
  .chapter-body ol.steps li b { color: var(--ink); }

  /* ---------- Endpoint blocks ---------- */
  .endpoint { border: 1px solid var(--border); border-radius: 16px; padding: 1.3rem 1.4rem; margin-block: 1.3rem; background: var(--paper-2); }
  .endpoint-head { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; }
  .method { font-family: ui-monospace, monospace; font-size: .72rem; font-weight: 700; letter-spacing: .03em; padding: .25rem .6rem; border-radius: 7px; }
  .method-get { color: var(--get); background: var(--get-bg); }
  .method-post { color: var(--post); background: var(--post-bg); }
  .method-delete { color: var(--delete); background: var(--delete-bg); }
  .endpoint-path { font-family: ui-monospace, monospace; font-size: .88rem; direction: ltr; unicode-bidi: isolate; }
  .endpoint-perm { margin-inline-start: auto; font-size: .72rem; color: var(--ink-faint); font-family: ui-monospace, monospace; direction: ltr; unicode-bidi: isolate; }
  .endpoint-desc { color: var(--ink-soft); font-size: .9rem; margin-top: .7rem; }
  .code-cols { display: grid; grid-template-columns: 1fr 1fr; gap: .9rem; margin-top: .9rem; }
  .code-block { min-width: 0; }
  .code-block.full { grid-column: 1 / -1; }
  .code-label { font-size: .7rem; letter-spacing: .04em; text-transform: uppercase; color: var(--ink-faint); font-family: ui-monospace, monospace; margin-bottom: .35rem; }
  pre {
    margin: 0; background: var(--code-bg); color: var(--code-ink); border-radius: 10px;
    padding: .85rem .95rem; font-size: .78rem; line-height: 1.6; overflow-x: auto;
    direction: ltr; unicode-bidi: isolate; text-align: left;
  }
  pre code { font-family: ui-monospace, 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; }

  /* ---------- Callouts ---------- */
  .callout {
    display: flex; gap: .8rem; background: var(--paper-3); border: 1px solid var(--border);
    border-radius: 14px; padding: 1rem 1.2rem; margin-block: 1.3rem;
  }
  .callout svg { width: 20px; height: 20px; flex-shrink: 0; stroke: var(--amber-600); margin-top: .15rem; }
  .callout .label { font-family: ui-monospace, monospace; font-size: .68rem; letter-spacing: .04em; color: var(--amber-600); text-transform: uppercase; margin-bottom: .25rem; display: block; }
  .callout p { margin: 0; color: var(--ink); font-size: .88rem; }

  /* ---------- Reference / parameter tables ---------- */
  table.ref { width: 100%; border-collapse: collapse; margin-block: 1rem; font-size: .86rem; }
  table.ref th, table.ref td { text-align: right; padding: .55rem .7rem; border-bottom: 1px solid var(--border-soft); vertical-align: top; }
  table.ref th { color: var(--ink-faint); font-size: .72rem; text-transform: uppercase; letter-spacing: .03em; font-family: ui-monospace, monospace; }
  table.ref td.mono, table.ref code { font-family: ui-monospace, monospace; direction: ltr; unicode-bidi: isolate; }
  table.ref td.req-yes { color: var(--post); font-size: .78rem; font-weight: 700; }
  table.ref td.req-no { color: var(--ink-faint); font-size: .78rem; }
  .params-label { font-size: .74rem; letter-spacing: .03em; color: var(--ink-faint); text-transform: uppercase; font-family: ui-monospace, monospace; margin-top: 1.1rem; margin-bottom: -.4rem; display: block; }

  .colophon { display: flex; align-items: center; gap: .9rem; padding-block-start: 3rem; margin-top: 3rem; border-top: 1px solid var(--border-soft); }
  .colophon .stack { font-size: .8rem; color: var(--ink-faint); }
  .colophon .stack div:first-child { color: var(--ink-soft); margin-bottom: .3rem; }

  @media (max-width: 640px) {
    .code-cols { grid-template-columns: 1fr; }
    .toc ol { grid-template-columns: 1fr; }
    .endpoint-perm { margin-inline-start: 0; }
    table.ref { font-size: .78rem; }
  }
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
    <span class="doc-kicker">External API Reference — v1</span>
  </div>

  <!-- ============ COVER ============ -->
  <div class="cover">
    <span class="eyebrow">مستندات فنی</span>
    <h1>REST API سامانه‌ی FarsiWP</h1>
    <p class="lede">
      مجموعه‌ای از APIهای REST نسخه‌بندی‌شده که سامانه‌های بیرونی (وب‌سایت رزرو، گیت‌وی پرداخت، اسکنر گیت مستقل،
      فرم‌های آنلاین و ابزارهای شخص‌ثالث) را به هسته‌ی هتلداری، فروش بلیط، CRM، فرم‌ساز و مدیریت اسناد سامانه متصل می‌کند.
      هر درخواست JSON روی HTTPS است، با همان قرارداد خطا و همان مدل احراز هویت در همه‌ی بخش‌ها. این نسخه شامل
      راهنمای کامل هر endpoint (پارامترها، محدودیت‌ها، نمونه‌ی curl و پاسخ) به‌همراه یک راهنمای شروع سریع است.
    </p>
    <div class="meta-row">
      <span>پروتکل <b>REST · JSON over HTTPS</b></span>
      <span>نسخه‌بندی‌شده زیر <b class="mono">/api/v1/external</b></span>
      <span>احراز هویت با <b class="mono">X-API-Key / X-API-Secret</b></span>
      <span>عملیات‌های تغییردهنده <b>Idempotent</b></span>
    </div>
  </div>

  <!-- ============ TOC ============ -->
  <nav class="toc" aria-label="فهرست بخش‌ها">
    <h2>فهرست بخش‌ها</h2>
    <ol>
      <li><a href="#ch-00">احراز هویت، خطاها و Idempotency</a></li>
      <li><a href="#ch-quickstart">راهنمای شروع سریع</a></li>
      <li><a href="#ch-01">فروش بلیط و گیشه (POS)</a></li>
      <li><a href="#ch-02">اقامتی و هتلداری</a></li>
      <li><a href="#ch-03">CRM و سرنخ‌ها</a></li>
      <li><a href="#ch-04">فرم‌ساز (ثبت عمومی فرم)</a></li>
      <li><a href="#ch-05">مدیریت اسناد</a></li>
      <li><a href="#ch-06">وب‌هوک‌های خروجی</a></li>
      <li><a href="#ch-07">مرجع کدهای خطا</a></li>
      <li><a href="#ch-08">مرجع کامل مجوزها (Operations)</a></li>
    </ol>
  </nav>

  <!-- ============ 00 — AUTH, ERRORS, IDEMPOTENCY ============ -->
  <section class="chapter" id="ch-00">
    <div class="chapter-head">
      <div class="chapter-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l8 4v6c0 5-3.4 8.4-8 10-4.6-1.6-8-5-8-10V6l8-4Z"/></svg>
      </div>
      <div>
        <span class="chapter-num">بخش ۰۰</span>
        <h2>احراز هویت، خطاها و Idempotency</h2>
      </div>
    </div>
    <div class="chapter-body">
      <p class="lede">
        هر اکانت API (<span class="mono">ExternalApiClient</span>) یک جفت <span class="mono">api_key</span>/
        <span class="mono">api_secret</span> دارد. این‌ها هرگز جای نشست کاربر پنل را نمی‌گیرند - مخصوص سامانه‌های
        بیرونی‌اند، نه پرسنل. آدرس پایه‌ی همه‌ی مسیرهای این مستندات:
      </p>
      <pre><code>https://your-domain.example/api/v1/external</code></pre>

      <div class="code-cols" style="margin-top:1.3rem">
        <div class="code-block">
          <div class="code-label">هدرهای لازم برای هر درخواست</div>
          <pre><code>X-API-Key: pk_xxxxxxxxxxxxxxxx
X-API-Secret: your-plain-secret
Content-Type: application/json</code></pre>
        </div>
        <div class="code-block">
          <div class="code-label">شکل یکسان خطاها (هر کد وضعیت ≥ 400)</div>
          <pre><code>{
  "error": {
    "code": "FORBIDDEN",
    "message": "این اکانت مجاز به این عملیات نیست."
  }
}</code></pre>
        </div>
      </div>

      <h3>گام‌های احراز هویت (چه اتفاقی روی هر درخواست می‌افتد)</h3>
      <ol class="steps">
        <li><b>۱.</b> هدرهای <span class="mono">X-API-Key</span> و <span class="mono">X-API-Secret</span> بررسی می‌شوند؛ نبودشان <span class="mono">401 UNAUTHORIZED</span> می‌دهد.</li>
        <li><b>۲.</b> اکانتی با همان <span class="mono">api_key</span> پیدا می‌شود؛ اگر نبود، غیرفعال بود، یا رمز اشتباه بود، همان <span class="mono">401 UNAUTHORIZED</span> برمی‌گردد (عمداً بدون افشای اینکه کدام‌یک اشتباه است).</li>
        <li><b>۳.</b> سقف نرخ درخواست آن اکانت بررسی می‌شود (بخش بعد را ببینید).</li>
        <li><b>۴.</b> اکانت مجاز روی درخواست قرار می‌گیرد و کنترلر مجوز عملیات مشخص (مثل <span class="mono">ticket.issue</span>) را جداگانه بررسی می‌کند؛ نبود آن مجوز <span class="mono">403 FORBIDDEN</span> می‌دهد.</li>
      </ol>

      <h3>محدودیت نرخ درخواست (Rate Limiting)</h3>
      <p>
        هر اکانت یک سقف <span class="mono">rate_limit_per_minute</span> دارد (پیش‌فرض ۶۰، از پنل «اکانت‌های API خارجی» قابل تغییر است).
        شمارش به‌صورت یک پنجره‌ی ۶۰ ثانیه‌ای، جداگانه برای هر اکانت (بر اساس شناسه‌ی اکانت، نه IP) انجام می‌شود. عبور از سقف،
        بدون نیاز به هیچ هدر خاصی، پاسخ زیر را می‌دهد:
      </p>
      <pre><code>HTTP/1.1 429 Too Many Requests
{
  "error": { "code": "RATE_LIMITED", "message": "تعداد درخواست‌ها بیش از حد مجاز است." }
}</code></pre>
      <p>در نسخه‌ی فعلی هدرهای <span class="mono">X-RateLimit-*</span> در پاسخ برگردانده نمی‌شوند؛ سقف باید از پیش با پنل هماهنگ شود.</p>

      <div class="callout">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
        <div>
          <span class="label">Idempotency-Key</span>
          <p>
            هر متد POST‌ی که چیزی می‌سازد (بلیط، رزرو، سرنخ، سند، بازپرداخت، ثبت فرم) به هدر
            <span class="mono">Idempotency-Key</span> نیاز دارد - نبودش <span class="mono">400 INVALID_REQUEST</span> می‌دهد.
            هر رشته‌ی یکتا (پیشنهاد: یک UUID v4 که همان سمت شما، پیش از فرستادن درخواست، ساخته می‌شود) قابل قبول است.
            رفتار دقیق:
          </p>
        </div>
      </div>
      <table class="ref">
        <thead><tr><th>وضعیت درخواست تکراری</th><th>پاسخ</th></tr></thead>
        <tbody>
          <tr><td>همان کلید + همان بدنه‌ی دقیق درخواست</td><td>همان پاسخ اولین بار (همان کد وضعیت و همان بدنه) دوباره و بدون ساخت رکورد جدید برگردانده می‌شود.</td></tr>
          <tr><td>همان کلید + بدنه‌ی متفاوت</td><td><span class="mono">409 CONFLICT</span> - این کلید قبلاً با محتوای دیگری استفاده شده است.</td></tr>
          <tr><td>دو درخواست هم‌زمان با کلید و بدنه‌ی یکسان</td><td>هر دو یک رکورد می‌سازند تا زمانی که یکی commit شود؛ دیگری به‌طور خودکار پاسخ همان رکورد را برمی‌گرداند (بدون رکورد تکراری).</td></tr>
          <tr><td>کلید تازه (هنوز دیده نشده)</td><td>درخواست به‌طور عادی پردازش و رکورد ساخته می‌شود.</td></tr>
        </tbody>
      </table>
      <p>کلیدها به‌صورت دائمی نگه‌داشته می‌شوند - از یک کلید فقط یک‌بار، برای یک عملیات مشخص، استفاده کنید (مثلاً UUID تصادفی برای هر تراکنش واقعی، نه یک مقدار ثابت).</p>

      <p style="margin-top:1.4rem">
        هر عملیات به یک مجوز مشخص روی اکانت نیاز دارد (فهرست کامل در <a href="#ch-08">بخش ۰۸</a>)؛ نبود آن مجوز
        <span class="mono">403 FORBIDDEN</span> می‌دهد. هر رکورد ساخته‌شده از طریق API (بلیط، رزرو، سرنخ، سند، فرم) فقط برای
        همان اکانتی که آن را ساخته قابل خواندن است - رکوردهای ساخته‌شده در پنل مدیریت، یا توسط اکانت دیگری، هرگز از این API
        دیده نمی‌شوند (پاسخ <span class="mono">404</span>، نه ۴۰۳، تا وجود یا عدم‌وجود رکورد فاش نشود).
      </p>
    </div>
  </section>

  <!-- ============ QUICK START ============ -->
  <section class="chapter" id="ch-quickstart">
    <div class="chapter-head">
      <div class="chapter-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 3 14h8l-1 8 10-12h-8l1-8Z"/></svg>
      </div>
      <div>
        <span class="chapter-num">راهنمای شروع سریع</span>
        <h2>از صفر تا اولین درخواست موفق</h2>
      </div>
    </div>
    <div class="chapter-body">
      <ol class="steps">
        <li><b>۱. ساخت اکانت API.</b> در پنل مدیریت، از منوی کاربری وارد «اکانت‌های API خارجی» شوید (زیرساخت سازمانی) و یک اکانت جدید بسازید: نام سامانه، عملیات‌های مجاز، سقف نرخ درخواست، و در صورت نیاز کاربر «ثبت به نام» (برای ثبت فرم). رمز فقط همان یک لحظه نمایش داده می‌شود - همان‌جا کپی و در جای امن (Vault، متغیر محیطی) ذخیره کنید.</li>
        <li><b>۲. یک درخواست تستیِ ساده (GET) بزنید</b> تا احراز هویت را تأیید کنید:
          <pre><code>curl -s https://your-domain.example/api/v1/external/products \
  -H "X-API-Key: pk_xxxxxxxxxxxxxxxx" \
  -H "X-API-Secret: your-plain-secret"</code></pre>
          پاسخ موفق یک آرایه‌ی <span class="mono">data</span> برمی‌گرداند (حتی اگر خالی باشد). اگر <span class="mono">401</span> گرفتید، کلید/رمز یا فعال‌بودن اکانت را بررسی کنید.
        </li>
        <li><b>۳. یک POST را با Idempotency-Key امتحان کنید</b> - مثلاً ثبت یک سرنخ آزمایشی (بخش CRM):
          <pre><code>curl -s -X POST https://your-domain.example/api/v1/external/deals \
  -H "X-API-Key: pk_xxxxxxxxxxxxxxxx" \
  -H "X-API-Secret: your-plain-secret" \
  -H "Idempotency-Key: $(uuidgen)" \
  -H "Content-Type: application/json" \
  -d '{"title": "تست اتصال"}'</code></pre>
        </li>
        <li><b>۴. همان درخواست را دوباره با همان Idempotency-Key بفرستید</b> و مطمئن شوید دقیقاً همان پاسخ (و نه یک سرنخ تکراری) برمی‌گردد - این رفتار صحیح است، نه یک باگ.</li>
        <li><b>۵. سراغ endpointهای دامنه‌ی مورد نیازتان بروید</b> (بخش‌های ۰۱ تا ۰۶) و مطمئن شوید عملیات مربوطه در «عملیات‌های مجاز» اکانتتان فعال است؛ در غیر این صورت <span class="mono">403 FORBIDDEN</span> می‌گیرید.</li>
      </ol>
      <div class="callout">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
        <div>
          <span class="label">محیط آزمایشی جداگانه‌ای وجود ندارد</span>
          <p>این API محیط sandbox مجزا ندارد؛ هر درخواست روی همان دیتابیس واقعی اجرا می‌شود. برای تست، یک اکانت API جداگانه با فقط عملیات‌های لازم بسازید و رکوردهای تستی را بعداً از پنل پاک کنید.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ 01 — TICKETING / POS ============ -->
  <section class="chapter" id="ch-01">
    <div class="chapter-head">
      <div class="chapter-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4V8Z"/></svg>
      </div>
      <div>
        <span class="chapter-num">بخش ۰۱</span>
        <h2>فروش بلیط و گیشه (POS)</h2>
      </div>
    </div>
    <div class="chapter-body">
      <p class="lede">صدور بلیط، اعتبارسنجی کد تخفیف، بازپرداخت و اسکن مستقل گیت - برای یک گیشه‌ی فروش بیرونی یا اسکنر آفلاین.</p>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-get">GET</span>
          <span class="endpoint-path">/products</span>
          <span class="endpoint-perm">product.read</span>
        </div>
        <div class="endpoint-desc">فهرست محصولات (رویداد/تور) فعال قابل فروش برای این اکانت - اگر اکانت به محصولات خاصی محدود شده باشد، فقط همان‌ها برمی‌گردد.</div>
        <div class="code-block full">
          <div class="code-label">curl</div>
          <pre><code>curl -s https://your-domain.example/api/v1/external/products \
  -H "X-API-Key: $KEY" -H "X-API-Secret: $SECRET"</code></pre>
        </div>
        <span class="params-label">پاسخ · Response fields</span>
        <table class="ref">
          <thead><tr><th>فیلد</th><th>نوع</th><th>توضیح</th></tr></thead>
          <tbody>
            <tr><td class="mono">data[].id</td><td class="mono">int</td><td>شناسه‌ی محصول.</td></tr>
            <tr><td class="mono">data[].code</td><td class="mono">string</td><td>کد یکتای محصول.</td></tr>
            <tr><td class="mono">data[].name</td><td class="mono">string</td><td>نام محصول.</td></tr>
            <tr><td class="mono">data[].status</td><td class="mono">string</td><td><span class="mono">active</span> یا <span class="mono">inactive</span>.</td></tr>
            <tr><td class="mono">data[].variants[]</td><td class="mono">array</td><td>انواع بلیت فعال این محصول: <span class="mono">id</span>, <span class="mono">code</span>, <span class="mono">name</span>.</td></tr>
          </tbody>
        </table>
      </div>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-get">GET</span>
          <span class="endpoint-path">/ticket-variants?product_id=</span>
          <span class="endpoint-perm">ticket_variant.read</span>
        </div>
        <div class="endpoint-desc">فهرست انواع بلیت فعال، با فیلتر اختیاری روی محصول.</div>
        <span class="params-label">پارامترهای Query</span>
        <table class="ref">
          <thead><tr><th>پارامتر</th><th>نوع</th><th>الزامی</th><th>توضیح</th></tr></thead>
          <tbody>
            <tr><td class="mono">product_id</td><td class="mono">int</td><td class="req-no">خیر</td><td>فقط انواع بلیت همین محصول.</td></tr>
          </tbody>
        </table>
        <span class="params-label">پاسخ · Response fields</span>
        <table class="ref">
          <thead><tr><th>فیلد</th><th>نوع</th><th>توضیح</th></tr></thead>
          <tbody>
            <tr><td class="mono">data[].variant_id</td><td class="mono">int</td><td>شناسه‌ی نوع بلیت.</td></tr>
            <tr><td class="mono">data[].product_id</td><td class="mono">int</td><td>محصول والد.</td></tr>
            <tr><td class="mono">data[].name / code</td><td class="mono">string</td><td>نام و کد.</td></tr>
            <tr><td class="mono">data[].direction</td><td class="mono">string|null</td><td><span class="mono">outbound</span> یا <span class="mono">return</span>؛ <span class="mono">null</span> یعنی بدون محدودیت جهت.</td></tr>
            <tr><td class="mono">data[].allowed_uses</td><td class="mono">int</td><td>تعداد دفعات مجاز استفاده از هر بلیت این نوع.</td></tr>
            <tr><td class="mono">data[].price</td><td class="mono">int|null</td><td>قیمت فعلی به ریال - فقط اطلاعاتی؛ سرور همیشه قیمت واقعی را در لحظه‌ی صدور دوباره محاسبه می‌کند.</td></tr>
            <tr><td class="mono">data[].status</td><td class="mono">string</td><td><span class="mono">active</span> یا <span class="mono">inactive</span>.</td></tr>
          </tbody>
        </table>
      </div>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-post">POST</span>
          <span class="endpoint-path">/tickets</span>
          <span class="endpoint-perm">ticket.issue</span>
        </div>
        <div class="endpoint-desc">صدور یک یا چند بلیط از یک نوع بلیت، در یک سفارش. نیازمند <span class="mono">Idempotency-Key</span>.</div>
        <span class="params-label">بدنه‌ی درخواست</span>
        <table class="ref">
          <thead><tr><th>فیلد</th><th>نوع</th><th>الزامی</th><th>توضیح</th></tr></thead>
          <tbody>
            <tr><td class="mono">product_id</td><td class="mono">int</td><td class="req-yes">بله</td><td>باید در محصولات مجاز این اکانت باشد.</td></tr>
            <tr><td class="mono">ticket_variant_id</td><td class="mono">int</td><td class="req-yes">بله</td><td>باید متعلق به همان <span class="mono">product_id</span> باشد.</td></tr>
            <tr><td class="mono">quantity</td><td class="mono">int</td><td class="req-no">خیر (پیش‌فرض ۱)</td><td>بین ۱ تا ۵۰.</td></tr>
            <tr><td class="mono">customer_name</td><td class="mono">string</td><td class="req-no">خیر</td><td>حداکثر ۲۵۵ کاراکتر.</td></tr>
            <tr><td class="mono">customer_phone</td><td class="mono">string</td><td class="req-no">خیر</td><td>حداکثر ۳۲ کاراکتر.</td></tr>
            <tr><td class="mono">discount_code</td><td class="mono">string</td><td class="req-no">خیر</td><td>حداکثر ۵۰ کاراکتر؛ در لحظه‌ی صدور اعتبارسنجی و مصرف می‌شود.</td></tr>
            <tr><td class="mono">external_order_id</td><td class="mono">string</td><td class="req-no">خیر</td><td>شناسه‌ی سفارش در سامانه‌ی شما، برای ردیابی متقابل.</td></tr>
            <tr><td class="mono">payment_reference</td><td class="mono">string</td><td class="req-no">خیر</td><td>اگر ارسال شود و مبلغ سفارش &gt; ۰ باشد، یک پرداخت «تسویه‌شده» با همین مرجع ثبت می‌شود.</td></tr>
          </tbody>
        </table>
        <div class="code-cols">
          <div class="code-block">
            <div class="code-label">curl</div>
            <pre><code>curl -s -X POST .../tickets \
  -H "X-API-Key: $KEY" -H "X-API-Secret: $SECRET" \
  -H "Idempotency-Key: $(uuidgen)" \
  -H "Content-Type: application/json" \
  -d '{
    "product_id": 5,
    "ticket_variant_id": 3,
    "quantity": 2,
    "discount_code": "SAVE10"
  }'</code></pre>
          </div>
          <div class="code-block">
            <div class="code-label">Response · 201</div>
            <pre><code>{
  "order_id": 481,
  "order_number": "ORD-...",
  "external_order_id": null,
  "final_amount": 360000,
  "currency": "IRR",
  "tickets": [
    { "ticket_id": 901,
      "ticket_number": "TKT-260927-000123",
      "qr_payload": "...",
      "valid_from": "2026-...",
      "valid_until": "2026-...",
      "allowed_uses": 1,
      "remaining_uses": 1 }
  ]
}</code></pre>
          </div>
        </div>
      </div>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-get">GET</span>
          <span class="endpoint-path">/tickets/{ticket_number}</span>
          <span class="endpoint-perm">ticket.read</span>
        </div>
        <div class="endpoint-desc">جزئیات یک بلیط با شماره‌ی آن - فقط اگر این اکانت آن را صادر کرده باشد.</div>
        <span class="params-label">پاسخ · Response fields</span>
        <table class="ref">
          <thead><tr><th>فیلد</th><th>نوع</th><th>توضیح</th></tr></thead>
          <tbody>
            <tr><td class="mono">ticket_id / ticket_number</td><td class="mono">int / string</td><td>شناسه‌ی داخلی و شماره‌ی چاپی بلیط.</td></tr>
            <tr><td class="mono">qr_payload</td><td class="mono">string</td><td>مقداری که باید عیناً در QR کد‌گذاری شود - نه ticket_number.</td></tr>
            <tr><td class="mono">product / ticket_variant</td><td class="mono">string</td><td>کد محصول و کد نوع بلیت.</td></tr>
            <tr><td class="mono">valid_from / valid_until</td><td class="mono">ISO 8601</td><td>بازه‌ی اعتبار.</td></tr>
            <tr><td class="mono">allowed_uses / remaining_uses</td><td class="mono">int</td><td>تعداد کل و باقی‌مانده‌ی مصرف مجاز.</td></tr>
          </tbody>
        </table>
      </div>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-post">POST</span>
          <span class="endpoint-path">/tickets/{ticket_number}/reprint</span>
          <span class="endpoint-perm">ticket.reprint</span>
        </div>
        <div class="endpoint-desc">ثبت یک لاگ چاپ مجدد و بازگرداندن همان بلیط/QR - هرگز بلیط جدید نمی‌سازد و اعتبار/مصرف را تغییر نمی‌دهد. بدنه‌ی درخواست ندارد؛ پاسخ دقیقاً مثل <span class="mono">GET /tickets/{ticket_number}</span> است.</div>
      </div>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-post">POST</span>
          <span class="endpoint-path">/tickets/{ticket_number}/refund</span>
          <span class="endpoint-perm">ticket.refund</span>
        </div>
        <div class="endpoint-desc">بازپرداخت یک بلیط (وضعیت آن Refunded می‌شود و دیگر از گیت رد نمی‌شود). نیازمند <span class="mono">Idempotency-Key</span>؛ بلیط قبلاً لغو یا مسترد‌شده <span class="mono">422 VALIDATION_ERROR</span> می‌دهد.</div>
        <span class="params-label">بدنه‌ی درخواست</span>
        <table class="ref">
          <thead><tr><th>فیلد</th><th>نوع</th><th>الزامی</th><th>توضیح</th></tr></thead>
          <tbody>
            <tr><td class="mono">reason</td><td class="mono">string</td><td class="req-no">خیر</td><td>حداکثر ۲۵۵ کاراکتر.</td></tr>
            <tr><td class="mono">reference</td><td class="mono">string</td><td class="req-no">خیر</td><td>حداکثر ۱۰۰ کاراکتر - مثلاً شماره‌ی پیگیری بانکی.</td></tr>
          </tbody>
        </table>
        <div class="code-cols">
          <div class="code-block">
            <div class="code-label">curl</div>
            <pre><code>curl -s -X POST .../tickets/TKT-.../refund \
  -H "X-API-Key: $KEY" -H "X-API-Secret: $SECRET" \
  -H "Idempotency-Key: $(uuidgen)" \
  -H "Content-Type: application/json" \
  -d '{"reason": "انصراف مشتری"}'</code></pre>
          </div>
          <div class="code-block">
            <div class="code-label">Response · 201</div>
            <pre><code>{
  "refund_id": 12,
  "ticket_number": "TKT-260927-000123",
  "amount": 150000,
  "reason": "انصراف مشتری",
  "reference": null,
  "refunded_at": "2026-09-27T10:00:00+00:00"
}</code></pre>
          </div>
        </div>
      </div>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-post">POST</span>
          <span class="endpoint-path">/discount-codes/validate</span>
          <span class="endpoint-perm">discount_code.validate</span>
        </div>
        <div class="endpoint-desc">پیش‌بررسی یک کد تخفیف روی یک نوع بلیط، بدون مصرف واقعی آن (بدون Idempotency-Key، چون هیچ رکوردی نمی‌سازد).</div>
        <span class="params-label">بدنه‌ی درخواست</span>
        <table class="ref">
          <thead><tr><th>فیلد</th><th>نوع</th><th>الزامی</th><th>توضیح</th></tr></thead>
          <tbody>
            <tr><td class="mono">code</td><td class="mono">string</td><td class="req-yes">بله</td><td>حداکثر ۵۰ کاراکتر.</td></tr>
            <tr><td class="mono">ticket_variant_id</td><td class="mono">int</td><td class="req-yes">بله</td><td>باید در محصولات مجاز این اکانت باشد.</td></tr>
            <tr><td class="mono">quantity</td><td class="mono">int</td><td class="req-no">خیر (پیش‌فرض ۱)</td><td>بین ۱ تا ۵۰ - برای محاسبه‌ی جمع مبلغ.</td></tr>
            <tr><td class="mono">customer_phone</td><td class="mono">string</td><td class="req-no">خیر</td><td>برای بررسی سقف استفاده‌ی هر مشتری، در صورت تنظیم‌شدن روی کد.</td></tr>
          </tbody>
        </table>
        <div class="code-cols">
          <div class="code-block">
            <div class="code-label">Request</div>
            <pre><code>{
  "code": "SAVE10",
  "ticket_variant_id": 3
}</code></pre>
          </div>
          <div class="code-block">
            <div class="code-label">Response · 200 (معتبر) / نامعتبر</div>
            <pre><code>{ "valid": true, "code": "SAVE10",
  "subtotal": 200000,
  "discount_amount": 20000,
  "final_amount": 180000 }

{ "valid": false, "reason": "این کد منقضی شده است." }</code></pre>
          </div>
        </div>
      </div>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-post">POST</span>
          <span class="endpoint-path">/gates/{gate_code}/scan</span>
          <span class="endpoint-perm">gate.scan</span>
        </div>
        <div class="endpoint-desc">اعتبارسنجی و مصرف یک بلیط در یک دروازه‌ی مشخص - برای اسکنرهای مستقل که کاربر پرسنلی وارد نشده‌اند. همیشه <span class="mono">200</span> برمی‌گرداند (حتی برای اسکن رد‌شده)؛ نتیجه در فیلد <span class="mono">result</span> است.</div>
        <span class="params-label">بدنه‌ی درخواست</span>
        <table class="ref">
          <thead><tr><th>فیلد</th><th>نوع</th><th>الزامی</th><th>توضیح</th></tr></thead>
          <tbody>
            <tr><td class="mono">qr_token</td><td class="mono">string</td><td class="req-yes">بله</td><td>مقدار خام <span class="mono">qr_payload</span> که روی QR کد‌گذاری شده.</td></tr>
            <tr><td class="mono">device_identifier</td><td class="mono">string</td><td class="req-no">خیر</td><td>حداکثر ۱۰۰ کاراکتر - برای ردیابی اینکه کدام دستگاه اسکن کرده.</td></tr>
          </tbody>
        </table>
        <div class="code-cols">
          <div class="code-block">
            <div class="code-label">Request</div>
            <pre><code>{ "qr_token": "..." }</code></pre>
          </div>
          <div class="code-block">
            <div class="code-label">Response · 200</div>
            <pre><code>{
  "result": "success",
  "reason": null,
  "ticket_number": "TKT-260927-000123",
  "usage_number": 1
}</code></pre>
          </div>
        </div>
        <span class="params-label">مقادیر ممکن برای result / reason</span>
        <table class="ref">
          <thead><tr><th>result</th><th>reason (وقتی result=rejected)</th></tr></thead>
          <tbody>
            <tr><td class="mono">success</td><td>—</td></tr>
            <tr><td class="mono" rowspan="10">rejected</td><td class="mono">invalid_qr</td></tr>
            <tr><td class="mono">expired</td></tr>
            <tr><td class="mono">already_used</td></tr>
            <tr><td class="mono">no_remaining_usage</td></tr>
            <tr><td class="mono">wrong_gate</td></tr>
            <tr><td class="mono">wrong_direction</td></tr>
            <tr><td class="mono">sequence_error</td></tr>
            <tr><td class="mono">cancelled</td></tr>
            <tr><td class="mono">refunded</td></tr>
            <tr><td class="mono">blocked</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <!-- ============ 02 — HOSPITALITY ============ -->
  <section class="chapter" id="ch-02">
    <div class="chapter-head">
      <div class="chapter-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21V8l9-5 9 5v13M3 21h18M9 21v-6h6v6"/></svg>
      </div>
      <div>
        <span class="chapter-num">بخش ۰۲</span>
        <h2>اقامتی و هتلداری</h2>
      </div>
    </div>
    <div class="chapter-body">
      <p class="lede">استعلام موجودی اتاق و ثبت رزرو - برای موتور رزرو آنلاین یا یک OTA بیرونی.</p>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-get">GET</span>
          <span class="endpoint-path">/rooms/availability?check_in=&check_out=&room_type_id=</span>
          <span class="endpoint-perm">room.read</span>
        </div>
        <div class="endpoint-desc">فهرست اتاق‌های فعال و آزاد در بازه‌ی تاریخی داده‌شده (اتاقی که یک رزروِ غیرلغو‌شده با این بازه هم‌پوشانی داشته باشد، در نتیجه نیست).</div>
        <span class="params-label">پارامترهای Query</span>
        <table class="ref">
          <thead><tr><th>پارامتر</th><th>نوع</th><th>الزامی</th><th>توضیح</th></tr></thead>
          <tbody>
            <tr><td class="mono">check_in</td><td class="mono">date</td><td class="req-yes">بله</td><td>فرمت <span class="mono">YYYY-MM-DD</span> (میلادی).</td></tr>
            <tr><td class="mono">check_out</td><td class="mono">date</td><td class="req-yes">بله</td><td>باید بعد از <span class="mono">check_in</span> باشد.</td></tr>
            <tr><td class="mono">room_type_id</td><td class="mono">int</td><td class="req-no">خیر</td><td>فیلتر روی نوع اتاق مشخص.</td></tr>
          </tbody>
        </table>
        <div class="code-cols">
          <div class="code-block">
            <div class="code-label">curl</div>
            <pre><code>curl -s -G .../rooms/availability \
  -H "X-API-Key: $KEY" -H "X-API-Secret: $SECRET" \
  --data-urlencode "check_in=2026-11-02" \
  --data-urlencode "check_out=2026-11-04"</code></pre>
          </div>
          <div class="code-block">
            <div class="code-label">Response · 200</div>
            <pre><code>{
  "data": [
    { "room_id": 14, "name": "اتاق ۱۰۱",
      "room_type": { "id": 2, "name": "دبل", "capacity": 2 },
      "price_for_stay": 4500000 }
  ]
}</code></pre>
          </div>
        </div>
      </div>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-post">POST</span>
          <span class="endpoint-path">/bookings</span>
          <span class="endpoint-perm">booking.create</span>
        </div>
        <div class="endpoint-desc">ثبت یک رزرو قطعی روی یک اتاق مشخص. نیازمند <span class="mono">Idempotency-Key</span>؛ در صورت هم‌زمانی دو درخواست روی یک اتاق و بازه‌ی هم‌پوشان، فقط یکی رزرو شده و دیگری <span class="mono">409 CONFLICT</span> می‌گیرد.</div>
        <span class="params-label">بدنه‌ی درخواست</span>
        <table class="ref">
          <thead><tr><th>فیلد</th><th>نوع</th><th>الزامی</th><th>توضیح</th></tr></thead>
          <tbody>
            <tr><td class="mono">room_id</td><td class="mono">int</td><td class="req-yes">بله</td><td>—</td></tr>
            <tr><td class="mono">check_in_date</td><td class="mono">date</td><td class="req-yes">بله</td><td>—</td></tr>
            <tr><td class="mono">check_out_date</td><td class="mono">date</td><td class="req-yes">بله</td><td>باید بعد از <span class="mono">check_in_date</span> باشد.</td></tr>
            <tr><td class="mono">adults_count</td><td class="mono">int</td><td class="req-no">خیر (پیش‌فرض ۱)</td><td>حداقل ۱.</td></tr>
            <tr><td class="mono">children_count</td><td class="mono">int</td><td class="req-no">خیر (پیش‌فرض ۰)</td><td>حداقل ۰.</td></tr>
            <tr><td class="mono">sales_channel_id</td><td class="mono">int</td><td class="req-no">خیر</td><td>در صورت ارسال، منبع رزرو <span class="mono">ota</span> ثبت می‌شود؛ در غیر این صورت <span class="mono">online</span>.</td></tr>
            <tr><td class="mono">notes</td><td class="mono">string</td><td class="req-no">خیر</td><td>حداکثر ۱۰۰۰ کاراکتر.</td></tr>
            <tr><td class="mono">guest</td><td class="mono">object</td><td class="req-yes">بله</td><td>—</td></tr>
            <tr><td class="mono">guest.name</td><td class="mono">string</td><td class="req-yes">بله</td><td>حداکثر ۲۵۵ کاراکتر.</td></tr>
            <tr><td class="mono">guest.mobile</td><td class="mono">string</td><td class="req-yes">بله</td><td>حداکثر ۳۲ کاراکتر - کلید یکتاسازی مهمان (اگر مهمانی با این موبایل موجود باشد، همان استفاده می‌شود).</td></tr>
            <tr><td class="mono">guest.national_code</td><td class="mono">string</td><td class="req-no">خیر</td><td>حداکثر ۲۰ کاراکتر.</td></tr>
            <tr><td class="mono">guest.email</td><td class="mono">string</td><td class="req-no">خیر</td><td>ایمیل معتبر، حداکثر ۲۵۵ کاراکتر.</td></tr>
          </tbody>
        </table>
        <div class="code-cols">
          <div class="code-block">
            <div class="code-label">curl</div>
            <pre><code>curl -s -X POST .../bookings \
  -H "X-API-Key: $KEY" -H "X-API-Secret: $SECRET" \
  -H "Idempotency-Key: $(uuidgen)" \
  -H "Content-Type: application/json" \
  -d '{
    "room_id": 14,
    "check_in_date": "2026-11-02",
    "check_out_date": "2026-11-05",
    "guest": {"name": "مریم احمدی", "mobile": "09121112233"}
  }'</code></pre>
          </div>
          <div class="code-block">
            <div class="code-label">Response · 201</div>
            <pre><code>{
  "booking_id": 91,
  "reference_code": "BKG-2609-0091",
  "status": "confirmed",
  "room": {"id": 14, "name": "اتاق ۱۰۱", "room_type": "دبل"},
  "guest": {"name": "مریم احمدی", "mobile": "09121112233"},
  "check_in_date": "2026-11-02",
  "check_out_date": "2026-11-05",
  "nights": 3,
  "total_amount": 4500000
}</code></pre>
          </div>
        </div>
        <p style="margin-top:.6rem">مقادیر ممکن برای <span class="mono">status</span>: <span class="mono">pending</span>, <span class="mono">confirmed</span>, <span class="mono">checked_in</span>, <span class="mono">checked_out</span>, <span class="mono">cancelled</span> (رزرو ساخته‌شده از این API همیشه با <span class="mono">confirmed</span> شروع می‌شود).</p>
      </div>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-get">GET</span>
          <span class="endpoint-path">/bookings/{reference_code}</span>
          <span class="endpoint-perm">booking.read</span>
        </div>
        <div class="endpoint-desc">جزئیات یک رزرو با کد مرجع آن - فقط اگر همین اکانت آن را ساخته باشد. پاسخ دقیقاً همان ساختار بالا را دارد.</div>
      </div>
    </div>
  </section>

  <!-- ============ 03 — CRM ============ -->
  <section class="chapter" id="ch-03">
    <div class="chapter-head">
      <div class="chapter-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2M10 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      </div>
      <div>
        <span class="chapter-num">بخش ۰۳</span>
        <h2>CRM و سرنخ‌ها</h2>
      </div>
    </div>
    <div class="chapter-body">
      <p class="lede">ثبت سرنخ‌های ورودی از سایت، لندینگ تبلیغاتی یا هر فرم بیرونی، مستقیم در همان قیف فروش داخلی.</p>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-post">POST</span>
          <span class="endpoint-path">/deals</span>
          <span class="endpoint-perm">deal.create</span>
        </div>
        <div class="endpoint-desc">ثبت یک سرنخ جدید (همیشه در مرحله‌ی <span class="mono">new_lead</span> شروع می‌شود)، با ساخت خودکار مشتری در صورت نبودش (بر اساس شماره موبایل). نیازمند <span class="mono">Idempotency-Key</span>.</div>
        <span class="params-label">بدنه‌ی درخواست</span>
        <table class="ref">
          <thead><tr><th>فیلد</th><th>نوع</th><th>الزامی</th><th>توضیح</th></tr></thead>
          <tbody>
            <tr><td class="mono">title</td><td class="mono">string</td><td class="req-yes">بله</td><td>حداکثر ۲۵۵ کاراکتر.</td></tr>
            <tr><td class="mono">value</td><td class="mono">int</td><td class="req-no">خیر</td><td>ارزش تخمینی معامله به ریال، حداقل ۰.</td></tr>
            <tr><td class="mono">source</td><td class="mono">string</td><td class="req-no">خیر (پیش‌فرض online)</td><td>یکی از: <span class="mono">walk_in</span>, <span class="mono">phone</span>, <span class="mono">online</span>, <span class="mono">referral</span>, <span class="mono">social_media</span>, <span class="mono">other</span>.</td></tr>
            <tr><td class="mono">expected_close_date</td><td class="mono">date</td><td class="req-no">خیر</td><td>—</td></tr>
            <tr><td class="mono">notes</td><td class="mono">string</td><td class="req-no">خیر</td><td>حداکثر ۲۰۰۰ کاراکتر.</td></tr>
            <tr><td class="mono">guest</td><td class="mono">object</td><td class="req-no">خیر</td><td>اگر ارسال شود، <span class="mono">guest.name</span> و <span class="mono">guest.mobile</span> الزامی می‌شوند.</td></tr>
            <tr><td class="mono">guest.name</td><td class="mono">string</td><td class="req-no">با guest بله</td><td>حداکثر ۲۵۵ کاراکتر.</td></tr>
            <tr><td class="mono">guest.mobile</td><td class="mono">string</td><td class="req-no">با guest بله</td><td>حداکثر ۳۲ کاراکتر.</td></tr>
            <tr><td class="mono">guest.email</td><td class="mono">string</td><td class="req-no">خیر</td><td>ایمیل معتبر.</td></tr>
          </tbody>
        </table>
        <div class="code-cols">
          <div class="code-block">
            <div class="code-label">curl</div>
            <pre><code>curl -s -X POST .../deals \
  -H "X-API-Key: $KEY" -H "X-API-Secret: $SECRET" \
  -H "Idempotency-Key: $(uuidgen)" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "درخواست تور از سایت",
    "value": 5000000,
    "guest": {"name": "نیما کریمی", "mobile": "09129998877"}
  }'</code></pre>
          </div>
          <div class="code-block">
            <div class="code-label">Response · 201</div>
            <pre><code>{
  "deal_id": 233,
  "title": "درخواست تور از سایت",
  "stage": "new_lead",
  "value": 5000000,
  "guest": {"name": "نیما کریمی", "mobile": "09129998877"},
  "created_at": "2026-09-27T10:00:00+00:00"
}</code></pre>
          </div>
        </div>
        <p style="margin-top:.6rem">مراحل ممکن برای <span class="mono">stage</span> (فقط از داخل پنل تغییر می‌کند): <span class="mono">new_lead</span>, <span class="mono">needs_assessment</span>, <span class="mono">proposal_sent</span>, <span class="mono">negotiation</span>, <span class="mono">won</span>, <span class="mono">lost</span>. تغییر هرکدام، در صورت وجود اشتراک وب‌هوک <span class="mono">deal.stage_changed</span>، به شما اطلاع‌رسانی می‌شود (<a href="#ch-06">بخش ۰۶</a>).</p>
      </div>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-get">GET</span>
          <span class="endpoint-path">/deals/{id}</span>
          <span class="endpoint-perm">deal.read</span>
        </div>
        <div class="endpoint-desc">جزئیات یک سرنخ با شناسه‌ی آن - فقط اگر همین اکانت آن را ساخته باشد.</div>
      </div>
    </div>
  </section>

  <!-- ============ 04 — FORM BUILDER ============ -->
  <section class="chapter" id="ch-04">
    <div class="chapter-head">
      <div class="chapter-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12h6M9 16h6M9 8h6M6 2h9l5 5v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Z"/></svg>
      </div>
      <div>
        <span class="chapter-num">بخش ۰۴</span>
        <h2>فرم‌ساز (ثبت عمومی فرم)</h2>
      </div>
    </div>
    <div class="chapter-body">
      <p class="lede">
        درج مستقیم پاسخ یک فرم داخلی (قالب‌های ساخته‌شده در فرم‌ساز پنل) از یک وب‌سایت یا اپلیکیشن بیرونی - دقیقاً
        از همان مسیر تأیید/گردش‌کاری که یک ثبت داخلی طی می‌کند.
      </p>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-post">POST</span>
          <span class="endpoint-path">/form-templates/{form_template_id}/submissions</span>
          <span class="endpoint-perm">form_submission.create</span>
        </div>
        <div class="endpoint-desc">ثبت یک پاسخ جدید برای یک قالب فعال. فیلدهای نوع فایل/امضا/GPS/جدول از این مسیر پشتیبانی نمی‌شوند و اگر مقداری برایشان بفرستید یا الزامی باشند، <span class="mono">422 VALIDATION_ERROR</span> می‌گیرید. قالب غیرفعال یا ناموجود، <span class="mono">404 NOT_FOUND</span> می‌دهد.</div>
        <span class="params-label">بدنه‌ی درخواست</span>
        <table class="ref">
          <thead><tr><th>فیلد</th><th>نوع</th><th>الزامی</th><th>توضیح</th></tr></thead>
          <tbody>
            <tr><td class="mono">values</td><td class="mono">object</td><td class="req-no">خیر</td><td>نگاشت <span class="mono">field_key</span> ← مقدار، برای هر فیلد قالب. فیلدهای الزامیِ قالب باید مقدار داشته باشند وگرنه <span class="mono">422</span>.</td></tr>
          </tbody>
        </table>
        <div class="code-cols">
          <div class="code-block">
            <div class="code-label">curl</div>
            <pre><code>curl -s -X POST .../form-templates/7/submissions \
  -H "X-API-Key: $KEY" -H "X-API-Secret: $SECRET" \
  -H "Idempotency-Key: $(uuidgen)" \
  -H "Content-Type: application/json" \
  -d '{
    "values": {"full_name": "زهرا محمدی", "guests_count": 3}
  }'</code></pre>
          </div>
          <div class="code-block">
            <div class="code-label">Response · 201</div>
            <pre><code>{
  "submission_id": 77,
  "reference_code": "FRM-...",
  "status": "approved"
}</code></pre>
          </div>
        </div>
        <p style="margin-top:.6rem">مقادیر ممکن برای <span class="mono">status</span>: <span class="mono">draft</span>, <span class="mono">pending_approval</span>, <span class="mono">approved</span>, <span class="mono">rejected</span>, <span class="mono">cancelled</span>. اگر قالب گردش‌کار تأیید نداشته باشد، پاسخ همیشه <span class="mono">approved</span> است؛ در غیر این صورت <span class="mono">pending_approval</span> برمی‌گردد و مراحل بعدی در پنل طی می‌شود.</p>
      </div>

      <div class="callout">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
        <div>
          <span class="label">پیش‌نیاز اکانت</span>
          <p>
            اکانتی که این مسیر را صدا می‌زند باید یک «کاربر ثبت به نام» (<span class="mono">created_by</span>) تنظیم‌شده
            داشته باشد - در پنل، هنگام ساخت اکانت API. بدون آن، پاسخ <span class="mono">422 CONFIGURATION_ERROR</span>
            برمی‌گردد؛ چون بایگانی خودکار فرم تأییدشده به یک کاربر واقعی نیاز دارد.
          </p>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ 05 — DOCUMENTS ============ -->
  <section class="chapter" id="ch-05">
    <div class="chapter-head">
      <div class="chapter-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-7-7Z"/><path d="M13 2v7h7"/></svg>
      </div>
      <div>
        <span class="chapter-num">بخش ۰۵</span>
        <h2>مدیریت اسناد</h2>
      </div>
    </div>
    <div class="chapter-body">
      <p class="lede">بارگذاری و دریافت فایل، مستقیم در سیستم مدیریت اسناد پنل (پوشه‌ها، تاریخچه‌ی نسخه‌ها و سطح دسترسی یکسان).</p>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-post">POST</span>
          <span class="endpoint-path">/documents</span>
          <span class="endpoint-perm">document.upload</span>
        </div>
        <div class="endpoint-desc">بارگذاری یک فایل (<span class="mono">multipart/form-data</span>، نه JSON). نیازمند <span class="mono">Idempotency-Key</span> (کلید ترکیب‌شده با هش محتوای فایل، پس همان فایل با همان کلید دوباره سند تکراری نمی‌سازد).</div>
        <span class="params-label">فیلدهای فرم (multipart)</span>
        <table class="ref">
          <thead><tr><th>فیلد</th><th>نوع</th><th>الزامی</th><th>توضیح</th></tr></thead>
          <tbody>
            <tr><td class="mono">title</td><td class="mono">string</td><td class="req-yes">بله</td><td>حداکثر ۲۵۵ کاراکتر.</td></tr>
            <tr><td class="mono">file</td><td class="mono">file</td><td class="req-yes">بله</td><td>حداکثر حجم ۱۰٬۲۴۰ کیلوبایت (۱۰ مگابایت).</td></tr>
            <tr><td class="mono">access_level</td><td class="mono">string</td><td class="req-no">خیر (پیش‌فرض restricted)</td><td>یکی از: <span class="mono">public_internal</span>, <span class="mono">department_only</span>, <span class="mono">restricted</span>.</td></tr>
            <tr><td class="mono">department_id</td><td class="mono">int</td><td class="req-no">فقط با department_only بله</td><td>باید در جدول دپارتمان‌ها موجود باشد.</td></tr>
          </tbody>
        </table>
        <div class="code-cols">
          <div class="code-block">
            <div class="code-label">curl</div>
            <pre><code>curl -s -X POST .../documents \
  -H "X-API-Key: $KEY" -H "X-API-Secret: $SECRET" \
  -H "Idempotency-Key: $(uuidgen)" \
  -F "title=قرارداد اجاره اتاق" \
  -F "file=@/path/to/contract.pdf"</code></pre>
          </div>
          <div class="code-block">
            <div class="code-label">Response · 201</div>
            <pre><code>{
  "document_id": 58,
  "title": "قرارداد اجاره اتاق",
  "mime_type": "application/pdf",
  "size_kb": 214,
  "access_level": "restricted",
  "created_at": "2026-09-27T10:00:00+00:00"
}</code></pre>
          </div>
        </div>
      </div>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-get">GET</span>
          <span class="endpoint-path">/documents/{id}/download</span>
          <span class="endpoint-perm">document.read</span>
        </div>
        <div class="endpoint-desc">دریافت مستقیم فایل جاری یک سند (نه JSON، خودِ فایل) - فقط اگر همین اکانت آن را بارگذاری کرده باشد؛ سند دیگری (حتی ساخته‌شده در پنل) <span class="mono">404</span> می‌دهد.</div>
        <div class="code-block full">
          <div class="code-label">curl</div>
          <pre><code>curl -s -OJ .../documents/58/download \
  -H "X-API-Key: $KEY" -H "X-API-Secret: $SECRET"</code></pre>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ 06 — WEBHOOKS ============ -->
  <section class="chapter" id="ch-06">
    <div class="chapter-head">
      <div class="chapter-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a3 3 0 1 0-2.83-4H15a3 3 0 0 0 .1 6L9 15a3 3 0 1 0 1 2.24L15.9 12A3 3 0 0 0 18 8Z"/></svg>
      </div>
      <div>
        <span class="chapter-num">بخش ۰۶</span>
        <h2>وب‌هوک‌های خروجی</h2>
      </div>
    </div>
    <div class="chapter-body">
      <p class="lede">اطلاع‌رسانی لحظه‌ای رویدادهای کلیدی (اسکن موفق/ناموفق بلیط، ثبت رزرو، تغییر مرحله‌ی سرنخ) به سامانه‌ی بیرونی، به‌جای Polling. ارسال از طریق صف انجام می‌شود (تحویل تضمینی، اما نه لزوماً آنی).</p>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-post">POST</span>
          <span class="endpoint-path">/webhooks</span>
          <span class="endpoint-perm">webhook.manage</span>
        </div>
        <div class="endpoint-desc">ثبت یک اشتراک جدید. <span class="mono">signing_secret</span> فقط همین یک‌بار در پاسخ برگردانده می‌شود - در جای امن ذخیره کنید.</div>
        <span class="params-label">بدنه‌ی درخواست</span>
        <table class="ref">
          <thead><tr><th>فیلد</th><th>نوع</th><th>الزامی</th><th>توضیح</th></tr></thead>
          <tbody>
            <tr><td class="mono">event_type</td><td class="mono">string</td><td class="req-yes">بله</td><td>یکی از چهار مقدار جدول پایین.</td></tr>
            <tr><td class="mono">target_url</td><td class="mono">string</td><td class="req-yes">بله</td><td>URL معتبر، حداکثر ۲۰۴۸ کاراکتر - باید HTTPS پذیرا باشد.</td></tr>
          </tbody>
        </table>
        <div class="code-cols">
          <div class="code-block">
            <div class="code-label">curl</div>
            <pre><code>curl -s -X POST .../webhooks \
  -H "X-API-Key: $KEY" -H "X-API-Secret: $SECRET" \
  -H "Content-Type: application/json" \
  -d '{
    "event_type": "booking.created",
    "target_url": "https://example.com/hooks/bookings"
  }'</code></pre>
          </div>
          <div class="code-block">
            <div class="code-label">Response · 201</div>
            <pre><code>{
  "webhook_subscription_id": 4,
  "event_type": "booking.created",
  "target_url": "https://example.com/hooks/bookings",
  "is_active": true,
  "last_triggered_at": null,
  "created_at": "2026-09-27T10:00:00+00:00",
  "signing_secret": "ONLY-SHOWN-ONCE..."
}</code></pre>
          </div>
        </div>
      </div>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-get">GET</span>
          <span class="endpoint-path">/webhooks</span>
          <span class="endpoint-perm">webhook.manage</span>
        </div>
        <div class="endpoint-desc">فهرست اشتراک‌های همین اکانت (بدون <span class="mono">signing_secret</span> - آن فقط لحظه‌ی ساخت نمایش داده می‌شود).</div>
      </div>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-delete">DELETE</span>
          <span class="endpoint-path">/webhooks/{id}</span>
          <span class="endpoint-perm">webhook.manage</span>
        </div>
        <div class="endpoint-desc">لغو یک اشتراک؛ پاسخ <span class="mono">204</span> بدون بدنه. اشتراک اکانت دیگری <span class="mono">404</span> می‌دهد.</div>
      </div>

      <p style="margin-top:1.6rem"><b>رویدادهای پشتیبانی‌شده:</b></p>
      <table class="ref">
        <thead><tr><th>event_type</th><th>زمان ارسال</th><th>فیلدهای data</th></tr></thead>
        <tbody>
          <tr><td class="mono"><code>ticket.scanned</code></td><td>اسکن موفق یک بلیط در گیت</td><td class="mono">ticket_number, gate, result, reason, usage_number, scanned_at</td></tr>
          <tr><td class="mono"><code>ticket.rejected</code></td><td>رد شدن یک اسکن در گیت</td><td class="mono">ticket_number, gate, result, reason, usage_number, scanned_at</td></tr>
          <tr><td class="mono"><code>booking.created</code></td><td>ثبت یک رزرو جدید متعلق به همین اکانت</td><td class="mono">booking_id, reference_code, status, check_in, check_out</td></tr>
          <tr><td class="mono"><code>deal.stage_changed</code></td><td>تغییر مرحله‌ی یک سرنخ متعلق به همین اکانت</td><td class="mono">deal_id, title, stage, previous_stage</td></tr>
        </tbody>
      </table>

      <div class="code-cols" style="margin-top:.5rem">
        <div class="code-block">
          <div class="code-label">بدنه‌ی هر رویداد ارسالی (POST به target_url شما)</div>
          <pre><code>{
  "event": "ticket.scanned",
  "data": { "ticket_number": "...", "gate": "GATE-A", "result": "success" },
  "sent_at": "2026-09-27T10:00:00+00:00"
}</code></pre>
        </div>
        <div class="code-block">
          <div class="code-label">هدرهای این درخواست</div>
          <pre><code>X-Webhook-Event: ticket.scanned
X-Webhook-Signature: <hex hmac_sha256(body, signing_secret)>
Content-Type: application/json</code></pre>
        </div>
      </div>

      <h3>اعتبارسنجی امضا - نمونه‌ی کد</h3>
      <div class="code-cols">
        <div class="code-block">
          <div class="code-label">Node.js</div>
          <pre><code>const crypto = require('crypto');

function isValid(rawBody, signatureHeader, secret) {
  const expected = crypto
    .createHmac('sha256', secret)
    .update(rawBody) // raw string, before JSON.parse
    .digest('hex');
  return crypto.timingSafeEqual(
    Buffer.from(expected), Buffer.from(signatureHeader)
  );
}</code></pre>
        </div>
        <div class="code-block">
          <div class="code-label">PHP</div>
          <pre><code>$expected = hash_hmac('sha256', $rawBody, $secret);

if (! hash_equals($expected, $signatureHeader)) {
    abort(401, 'امضای نامعتبر');
}</code></pre>
        </div>
      </div>
      <div class="callout">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l8 4v6c0 5-3.4 8.4-8 10-4.6-1.6-8-5-8-10V6l8-4Z"/></svg>
        <div>
          <span class="label">نکته‌ی مهم</span>
          <p>
            امضا روی <b>بدنه‌ی خام</b> درخواست محاسبه شده - آن را روی همان بایت‌های خام دریافتی بازمحاسبه کنید، نه روی
            نسخه‌ای که دوباره <span class="mono">JSON.stringify</span> کرده‌اید (ترتیب کلیدها یا فاصله‌گذاری متفاوت،
            امضا را نامعتبر نشان می‌دهد). سرور شما باید فقط ۲xx برگرداند تا تحویل موفق ثبت شود؛ غیر ۲xx باعث تلاش مجدد
            (retry) خودکار می‌شود.
          </p>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ 07 — ERROR REFERENCE ============ -->
  <section class="chapter" id="ch-07">
    <div class="chapter-head">
      <div class="chapter-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
      </div>
      <div>
        <span class="chapter-num">بخش ۰۷</span>
        <h2>مرجع کدهای خطا</h2>
      </div>
    </div>
    <div class="chapter-body">
      <table class="ref">
        <thead><tr><th>HTTP</th><th>code</th><th>معنی</th></tr></thead>
        <tbody>
          <tr><td>400</td><td class="mono"><code>INVALID_REQUEST</code></td><td>هدر <span class="mono">Idempotency-Key</span> فرستاده نشده.</td></tr>
          <tr><td>401</td><td class="mono"><code>UNAUTHORIZED</code></td><td>کلید/رمز API نامعتبر یا اکانت غیرفعال است.</td></tr>
          <tr><td>403</td><td class="mono"><code>FORBIDDEN</code></td><td>اکانت مجوز این عملیات را ندارد.</td></tr>
          <tr><td>404</td><td class="mono"><code>NOT_FOUND</code></td><td>رکورد یافت نشد، یا متعلق به اکانت دیگری است.</td></tr>
          <tr><td>409</td><td class="mono"><code>CONFLICT</code></td><td>همان Idempotency-Key با بدنه‌ی متفاوت، یا تداخل منبع (مثلاً اتاق رزرو شده).</td></tr>
          <tr><td>422</td><td class="mono"><code>VALIDATION_ERROR</code></td><td>ورودی نامعتبر یا ناقص است.</td></tr>
          <tr><td>422</td><td class="mono"><code>CONFIGURATION_ERROR</code></td><td>پیش‌نیازی روی خود اکانت تنظیم نشده (مثلاً کاربر «ثبت به نام»).</td></tr>
          <tr><td>429</td><td class="mono"><code>RATE_LIMITED</code></td><td>سقف درخواست در دقیقه‌ی این اکانت رد شده.</td></tr>
        </tbody>
      </table>
      <p>خطاهای استاندارد اعتبارسنجی Laravel (۴۲۲) که مستقیماً از <span class="mono">$request->validate()</span> می‌آیند، ساختار native خودِ Laravel (<span class="mono">{"message": ..., "errors": {...}}</span>) دارند، نه لزوماً همیشه شکل <span class="mono">{"error": {...}}</span> بالا - هر دو شکل را در سمت کلاینت پشتیبانی کنید.</p>
    </div>
  </section>

  <!-- ============ 08 — PERMISSIONS REFERENCE ============ -->
  <section class="chapter" id="ch-08">
    <div class="chapter-head">
      <div class="chapter-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
      </div>
      <div>
        <span class="chapter-num">بخش ۰۸</span>
        <h2>مرجع کامل مجوزها (Operations)</h2>
      </div>
    </div>
    <div class="chapter-body">
      <p class="lede">هر اکانت API یک فهرست «عملیات‌های مجاز» دارد که از پنل «اکانت‌های API خارجی» تنظیم می‌شود. این جدول، نگاشت کامل هر مجوز به همان endpointـی است که آن را بررسی می‌کند.</p>
      <table class="ref">
        <thead><tr><th>operation</th><th>endpoint</th><th>توضیح</th></tr></thead>
        <tbody>
          <tr><td class="mono">product.read</td><td class="mono">GET /products</td><td>مشاهده‌ی محصولات گیشه بلیط.</td></tr>
          <tr><td class="mono">ticket_variant.read</td><td class="mono">GET /ticket-variants</td><td>مشاهده‌ی انواع بلیت.</td></tr>
          <tr><td class="mono">ticket.issue</td><td class="mono">POST /tickets</td><td>صدور بلیط.</td></tr>
          <tr><td class="mono">ticket.read</td><td class="mono">GET /tickets/{ticket_number}</td><td>مشاهده‌ی بلیط.</td></tr>
          <tr><td class="mono">ticket.reprint</td><td class="mono">POST /tickets/{ticket_number}/reprint</td><td>چاپ مجدد بلیط.</td></tr>
          <tr><td class="mono">ticket.refund</td><td class="mono">POST /tickets/{ticket_number}/refund</td><td>بازپرداخت بلیط.</td></tr>
          <tr><td class="mono">discount_code.validate</td><td class="mono">POST /discount-codes/validate</td><td>اعتبارسنجی کد تخفیف.</td></tr>
          <tr><td class="mono">gate.scan</td><td class="mono">POST /gates/{gate_code}/scan</td><td>اسکن مستقل گیت.</td></tr>
          <tr><td class="mono">room.read</td><td class="mono">GET /rooms/availability</td><td>مشاهده‌ی موجودی اتاق.</td></tr>
          <tr><td class="mono">booking.create</td><td class="mono">POST /bookings</td><td>ثبت رزرو.</td></tr>
          <tr><td class="mono">booking.read</td><td class="mono">GET /bookings/{reference_code}</td><td>مشاهده‌ی رزرو.</td></tr>
          <tr><td class="mono">deal.create</td><td class="mono">POST /deals</td><td>ثبت سرنخ (CRM).</td></tr>
          <tr><td class="mono">deal.read</td><td class="mono">GET /deals/{id}</td><td>مشاهده‌ی سرنخ (CRM).</td></tr>
          <tr><td class="mono">form_submission.create</td><td class="mono">POST /form-templates/{id}/submissions</td><td>ثبت پاسخ فرم.</td></tr>
          <tr><td class="mono">document.upload</td><td class="mono">POST /documents</td><td>بارگذاری سند.</td></tr>
          <tr><td class="mono">document.read</td><td class="mono">GET /documents/{id}/download</td><td>دانلود سند.</td></tr>
          <tr><td class="mono">webhook.manage</td><td class="mono">POST|GET|DELETE /webhooks</td><td>ثبت/فهرست/حذف اشتراک وب‌هوک.</td></tr>
        </tbody>
      </table>
      <div class="callout">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
        <div>
          <span class="label">اصل کمترین دسترسی</span>
          <p>فقط عملیات‌هایی که واقعاً استفاده می‌کنید را برای هر اکانت فعال کنید. علاوه بر این، یک اکانت می‌تواند به فهرست مشخصی از محصولات محدود شود (خالی = دسترسی به همه‌ی محصولات) - این محدودیت روی <span class="mono">product.read</span>، <span class="mono">ticket.issue</span> و <span class="mono">discount_code.validate</span> هم اعمال می‌شود.</p>
        </div>
      </div>
    </div>
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
      <div>External API v1 · Laravel 13</div>
      <div>هر اکانت API از پنل «اکانت‌های API خارجی» ساخته و مدیریت می‌شود.</div>
    </div>
  </div>

</div>
</body>
</html>
