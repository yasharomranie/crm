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
    max-width: 960px;
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
  .chapter-body p { color: var(--ink-soft); max-width: 70ch; }
  .chapter-body p.lede { font-size: 1rem; color: var(--ink); margin-bottom: 1.1rem; }

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

  /* ---------- Reference table ---------- */
  table.ref { width: 100%; border-collapse: collapse; margin-block: 1rem; font-size: .86rem; }
  table.ref th, table.ref td { text-align: right; padding: .55rem .7rem; border-bottom: 1px solid var(--border-soft); }
  table.ref th { color: var(--ink-faint); font-size: .72rem; text-transform: uppercase; letter-spacing: .03em; font-family: ui-monospace, monospace; }
  table.ref td.mono, table.ref code { font-family: ui-monospace, monospace; direction: ltr; unicode-bidi: isolate; }

  .colophon { display: flex; align-items: center; gap: .9rem; padding-block-start: 3rem; margin-top: 3rem; border-top: 1px solid var(--border-soft); }
  .colophon .stack { font-size: .8rem; color: var(--ink-faint); }
  .colophon .stack div:first-child { color: var(--ink-soft); margin-bottom: .3rem; }

  @media (max-width: 640px) {
    .code-cols { grid-template-columns: 1fr; }
    .toc ol { grid-template-columns: 1fr; }
    .endpoint-perm { margin-inline-start: 0; }
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
      هر درخواست JSON روی HTTPS است، با همان قرارداد خطا و همان مدل احراز هویت در همه‌ی بخش‌ها.
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
      <li><a href="#ch-01">فروش بلیط و گیشه (POS)</a></li>
      <li><a href="#ch-02">اقامتی و هتلداری</a></li>
      <li><a href="#ch-03">CRM و سرنخ‌ها</a></li>
      <li><a href="#ch-04">فرم‌ساز (ثبت عمومی فرم)</a></li>
      <li><a href="#ch-05">مدیریت اسناد</a></li>
      <li><a href="#ch-06">وب‌هوک‌های خروجی</a></li>
      <li><a href="#ch-07">مرجع کدهای خطا</a></li>
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

      <div class="callout">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
        <div>
          <span class="label">Idempotency-Key</span>
          <p>
            هر متد POST‌ی که چیزی می‌سازد (بلیط، رزرو، سرنخ، سند، بازپرداخت، ثبت فرم) به هدر
            <span class="mono">Idempotency-Key</span> نیاز دارد. اگر همان کلید دوباره با همان بدنه‌ی درخواست بیاید،
            دقیقاً همان پاسخ اول دوباره برگردانده می‌شود (نه ساخت دوباره)؛ همان کلید با بدنه‌ی متفاوت، خطای
            <span class="mono">409 CONFLICT</span> می‌دهد. این یعنی یک POST را می‌توان با خیال راحت، در صورت قطعی
            شبکه یا نیاز به تلاش مجدد، دوباره فرستاد.
          </p>
        </div>
      </div>

      <p>
        هر عملیات به یک مجوز مشخص روی اکانت (مثل <span class="mono">ticket.issue</span> یا
        <span class="mono">booking.create</span>) نیاز دارد؛ نبود آن مجوز، <span class="mono">403 FORBIDDEN</span>
        می‌دهد. هر رکورد ساخته‌شده از طریق API (بلیط، رزرو، سرنخ، سند، فرم) فقط برای همان اکانتی که آن را ساخته قابل
        خواندن است - رکوردهای ساخته‌شده در پنل مدیریت، یا توسط اکانت دیگری، هرگز از این API دیده نمی‌شوند
        (پاسخ <span class="mono">404</span>، نه ۴۰۳، تا وجود یا عدم‌وجود رکورد فاش نشود).
      </p>
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
        <div class="endpoint-desc">فهرست محصولات (رویداد/تور) قابل فروش برای این اکانت.</div>
      </div>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-get">GET</span>
          <span class="endpoint-path">/ticket-variants</span>
          <span class="endpoint-perm">ticket_variant.read</span>
        </div>
        <div class="endpoint-desc">فهرست انواع بلیط (نوع، ظرفیت، قیمت جاری) قابل صدور.</div>
      </div>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-post">POST</span>
          <span class="endpoint-path">/tickets</span>
          <span class="endpoint-perm">ticket.issue</span>
        </div>
        <div class="endpoint-desc">صدور یک یا چند بلیط در یک سفارش. نیازمند <span class="mono">Idempotency-Key</span>.</div>
        <div class="code-cols">
          <div class="code-block">
            <div class="code-label">Request</div>
            <pre><code>{
  "items": [
    { "ticket_variant_id": 3, "quantity": 2 }
  ],
  "discount_code": "SAVE10"
}</code></pre>
          </div>
          <div class="code-block">
            <div class="code-label">Response · 201</div>
            <pre><code>{
  "order_id": 481,
  "tickets": [
    { "ticket_number": "TKT-260927-000123",
      "qr_token": "..." }
  ],
  "total_amount": 360000
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
        <div class="endpoint-desc">جزئیات یک بلیط با شماره‌ی آن.</div>
      </div>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-post">POST</span>
          <span class="endpoint-path">/tickets/{ticket_number}/reprint</span>
          <span class="endpoint-perm">ticket.reprint</span>
        </div>
        <div class="endpoint-desc">صدور مجدد فایل چاپی همان بلیط، بدون تغییر در اعتبار یا مصرف آن.</div>
      </div>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-post">POST</span>
          <span class="endpoint-path">/tickets/{ticket_number}/refund</span>
          <span class="endpoint-perm">ticket.refund</span>
        </div>
        <div class="endpoint-desc">بازپرداخت یک بلیط (وضعیت آن Refunded می‌شود و دیگر از گیت رد نمی‌شود). نیازمند <span class="mono">Idempotency-Key</span>.</div>
        <div class="code-cols">
          <div class="code-block">
            <div class="code-label">Request</div>
            <pre><code>{ "reason": "انصراف مشتری" }</code></pre>
          </div>
          <div class="code-block">
            <div class="code-label">Response · 201</div>
            <pre><code>{ "refund_id": 12, "amount": 150000 }</code></pre>
          </div>
        </div>
      </div>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-post">POST</span>
          <span class="endpoint-path">/discount-codes/validate</span>
          <span class="endpoint-perm">discount_code.validate</span>
        </div>
        <div class="endpoint-desc">پیش‌بررسی یک کد تخفیف روی یک نوع بلیط، بدون مصرف آن (فقط محاسبه، بدون اثر جانبی).</div>
        <div class="code-cols">
          <div class="code-block">
            <div class="code-label">Request</div>
            <pre><code>{
  "code": "SAVE10",
  "ticket_variant_id": 3
}</code></pre>
          </div>
          <div class="code-block">
            <div class="code-label">Response · 200</div>
            <pre><code>{
  "valid": true,
  "subtotal": 200000,
  "discount_amount": 20000,
  "final_amount": 180000
}</code></pre>
          </div>
        </div>
      </div>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-post">POST</span>
          <span class="endpoint-path">/gates/{gate_code}/scan</span>
          <span class="endpoint-perm">gate.scan</span>
        </div>
        <div class="endpoint-desc">اعتبارسنجی و مصرف یک بلیط در یک دروازه‌ی مشخص - برای اسکنرهای مستقل که کاربر پرسنلی وارد نشده‌اند.</div>
        <div class="code-cols">
          <div class="code-block">
            <div class="code-label">Request</div>
            <pre><code>{ "qr_token": "..." }</code></pre>
          </div>
          <div class="code-block">
            <div class="code-label">Response · 200</div>
            <pre><code>{
  "result": "success",
  "ticket_number": "TKT-260927-000123"
}</code></pre>
          </div>
        </div>
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
        <div class="endpoint-desc">فهرست اتاق‌های آزاد در بازه‌ی تاریخی داده‌شده، با فیلتر اختیاری روی نوع اتاق.</div>
      </div>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-post">POST</span>
          <span class="endpoint-path">/bookings</span>
          <span class="endpoint-perm">booking.create</span>
        </div>
        <div class="endpoint-desc">ثبت یک رزرو قطعی روی یک اتاق مشخص. نیازمند <span class="mono">Idempotency-Key</span>؛ در صورت هم‌زمانی دو درخواست روی یک اتاق، فقط یکی رزرو می‌شود.</div>
        <div class="code-cols">
          <div class="code-block">
            <div class="code-label">Request</div>
            <pre><code>{
  "room_id": 14,
  "check_in_date": "1404-08-02",
  "check_out_date": "1404-08-05",
  "guest": {
    "name": "مریم احمدی",
    "mobile": "09121112233"
  }
}</code></pre>
          </div>
          <div class="code-block">
            <div class="code-label">Response · 201</div>
            <pre><code>{
  "booking_id": 91,
  "reference_code": "BKG-2609-0091",
  "status": "confirmed",
  "nights": 3,
  "total_amount": 4500000
}</code></pre>
          </div>
        </div>
      </div>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-get">GET</span>
          <span class="endpoint-path">/bookings/{reference_code}</span>
          <span class="endpoint-perm">booking.read</span>
        </div>
        <div class="endpoint-desc">جزئیات یک رزرو با کد مرجع آن.</div>
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
        <div class="endpoint-desc">ثبت یک سرنخ جدید (در مرحله‌ی «سرنخ جدید» قیف فروش)، با ساخت خودکار مشتری در صورت نبودش. نیازمند <span class="mono">Idempotency-Key</span>.</div>
        <div class="code-cols">
          <div class="code-block">
            <div class="code-label">Request</div>
            <pre><code>{
  "title": "درخواست تور از سایت",
  "value": 5000000,
  "guest": {
    "name": "نیما کریمی",
    "mobile": "09129998877"
  }
}</code></pre>
          </div>
          <div class="code-block">
            <div class="code-label">Response · 201</div>
            <pre><code>{
  "deal_id": 233,
  "title": "درخواست تور از سایت",
  "stage": "new_lead",
  "value": 5000000
}</code></pre>
          </div>
        </div>
      </div>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-get">GET</span>
          <span class="endpoint-path">/deals/{id}</span>
          <span class="endpoint-perm">deal.read</span>
        </div>
        <div class="endpoint-desc">جزئیات یک سرنخ با شناسه‌ی آن.</div>
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
        <div class="endpoint-desc">ثبت یک پاسخ جدید برای یک قالب فعال. فیلدهای نوع فایل/امضا/GPS/جدول از این مسیر پشتیبانی نمی‌شوند.</div>
        <div class="code-cols">
          <div class="code-block">
            <div class="code-label">Request</div>
            <pre><code>{
  "values": {
    "full_name": "زهرا محمدی",
    "guests_count": 3
  }
}</code></pre>
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
        <div class="endpoint-desc">
          بارگذاری یک فایل (<span class="mono">multipart/form-data</span>). نیازمند <span class="mono">Idempotency-Key</span>.
          سطح دسترسی پیش‌فرض <span class="mono">restricted</span> است.
        </div>
        <div class="code-cols">
          <div class="code-block">
            <div class="code-label">Request (multipart fields)</div>
            <pre><code>title: قرارداد اجاره اتاق
file: (binary)
access_level: restricted</code></pre>
          </div>
          <div class="code-block">
            <div class="code-label">Response · 201</div>
            <pre><code>{
  "document_id": 58,
  "title": "قرارداد اجاره اتاق",
  "mime_type": "application/pdf",
  "size_kb": 214
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
        <div class="endpoint-desc">دریافت فایل جاری یک سند - فقط اگر همین اکانت آن را بارگذاری کرده باشد.</div>
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
      <p class="lede">اطلاع‌رسانی لحظه‌ای رویدادهای کلیدی (اسکن موفق/ناموفق بلیط، ثبت رزرو، تغییر مرحله‌ی سرنخ) به سامانه‌ی بیرونی، به‌جای Polling.</p>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-post">POST</span>
          <span class="endpoint-path">/webhooks</span>
          <span class="endpoint-perm">webhook.manage</span>
        </div>
        <div class="endpoint-desc">ثبت یک اشتراک جدید. <span class="mono">signing_secret</span> فقط همین یک بار برگردانده می‌شود.</div>
        <div class="code-cols">
          <div class="code-block">
            <div class="code-label">Request</div>
            <pre><code>{
  "event_type": "booking.created",
  "target_url": "https://example.com/hooks/bookings"
}</code></pre>
          </div>
          <div class="code-block">
            <div class="code-label">Response · 201</div>
            <pre><code>{
  "webhook_subscription_id": 4,
  "event_type": "booking.created",
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
        <div class="endpoint-desc">فهرست اشتراک‌های همین اکانت (بدون secret).</div>
      </div>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-delete">DELETE</span>
          <span class="endpoint-path">/webhooks/{id}</span>
          <span class="endpoint-perm">webhook.manage</span>
        </div>
        <div class="endpoint-desc">لغو یک اشتراک.</div>
      </div>

      <p style="margin-top:1.6rem"><b>رویدادهای پشتیبانی‌شده:</b></p>
      <table class="ref">
        <thead><tr><th>event_type</th><th>زمان ارسال</th></tr></thead>
        <tbody>
          <tr><td class="mono"><code>ticket.scanned</code></td><td>اسکن موفق یک بلیط در گیت</td></tr>
          <tr><td class="mono"><code>ticket.rejected</code></td><td>رد شدن یک اسکن در گیت</td></tr>
          <tr><td class="mono"><code>booking.created</code></td><td>ثبت یک رزرو جدید متعلق به همین اکانت</td></tr>
          <tr><td class="mono"><code>deal.stage_changed</code></td><td>تغییر مرحله‌ی یک سرنخ متعلق به همین اکانت</td></tr>
        </tbody>
      </table>

      <div class="code-cols" style="margin-top:.5rem">
        <div class="code-block">
          <div class="code-label">بدنه‌ی هر رویداد ارسالی</div>
          <pre><code>{
  "event": "ticket.scanned",
  "data": { "ticket_number": "...", "gate": "GATE-A", "result": "success" },
  "sent_at": "2026-09-27T10:00:00+00:00"
}</code></pre>
        </div>
        <div class="code-block">
          <div class="code-label">هدر امضا (روی بدنه‌ی خام JSON بالا)</div>
          <pre><code>X-Webhook-Event: ticket.scanned
X-Webhook-Signature: hmac_sha256(body, signing_secret)</code></pre>
        </div>
      </div>

      <div class="callout">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l8 4v6c0 5-3.4 8.4-8 10-4.6-1.6-8-5-8-10V6l8-4Z"/></svg>
        <div>
          <span class="label">اعتبارسنجی امضا</span>
          <p>
            <span class="mono">X-Webhook-Signature</span> برابر است با
            <span class="mono">HMAC-SHA256(بدنه‌ی خام درخواست, signing_secret)</span> به‌صورت hex. آن را با همان
            الگوریتم روی بدنه‌ی خامی که دریافت کرده‌اید (نه یک JSON.stringify دوباره) بازمحاسبه و با هدر مقایسه کنید.
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
          <tr><td>401</td><td class="mono"><code>UNAUTHENTICATED</code></td><td>کلید/رمز API نامعتبر یا اکانت غیرفعال است.</td></tr>
          <tr><td>403</td><td class="mono"><code>FORBIDDEN</code></td><td>اکانت مجوز این عملیات را ندارد.</td></tr>
          <tr><td>404</td><td class="mono"><code>NOT_FOUND</code></td><td>رکورد یافت نشد، یا متعلق به اکانت دیگری است.</td></tr>
          <tr><td>409</td><td class="mono"><code>CONFLICT</code></td><td>همان Idempotency-Key با بدنه‌ی متفاوت، یا تداخل منبع (مثلاً اتاق رزرو شده).</td></tr>
          <tr><td>422</td><td class="mono"><code>VALIDATION_ERROR</code></td><td>ورودی نامعتبر یا ناقص است.</td></tr>
          <tr><td>422</td><td class="mono"><code>CONFIGURATION_ERROR</code></td><td>پیش‌نیازی روی خود اکانت تنظیم نشده (مثلاً کاربر «ثبت به نام»).</td></tr>
          <tr><td>429</td><td class="mono"><code>RATE_LIMITED</code></td><td>سقف درخواست در دقیقه‌ی این اکانت رد شده.</td></tr>
        </tbody>
      </table>
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
      <div>هر اکانت API از پنل «اکانت‌های API» ساخته و مدیریت می‌شود.</div>
    </div>
  </div>

</div>
</body>
</html>
