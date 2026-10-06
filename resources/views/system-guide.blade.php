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

  html, body { margin: 0; padding: 0; overflow-x: hidden; }

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
    overflow-wrap: anywhere;
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

  /* ---------- REST API reference (ch-13) ---------- */
  .chapter-body h3 { font-size: 1.02rem; font-weight: 700; margin-top: 2rem; margin-bottom: .6rem; scroll-margin-top: 1.5rem; }
  .chapter-body ol.steps { margin: 0; padding-inline-start: 1.4rem; display: flex; flex-direction: column; gap: .7rem; }
  .chapter-body ol.steps li { color: var(--ink-soft); font-size: .94rem; }
  .chapter-body ol.steps li b { color: var(--ink); }
  .subtoc { display: flex; flex-wrap: wrap; gap: .5rem .9rem; margin-top: 1.1rem; padding: 0; list-style: none; }
  .subtoc a {
    font-size: .82rem; color: var(--ink-soft); text-decoration: none;
    background: var(--paper-2); border: 1px solid var(--border); border-radius: 999px; padding: .3rem .8rem;
  }
  .subtoc a:hover { color: var(--amber-600); }

  .endpoint { border: 1px solid var(--border); border-radius: 16px; padding: 1.3rem 1.4rem; margin-block: 1.3rem; background: var(--paper-2); }
  .endpoint-head { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; }
  .method { font-family: ui-monospace, monospace; font-size: .72rem; font-weight: 700; letter-spacing: .03em; padding: .25rem .6rem; border-radius: 7px; }
  .method-get { color: var(--get); background: var(--get-bg); }
  .method-post { color: var(--post); background: var(--post-bg); }
  .method-delete { color: var(--delete); background: var(--delete-bg); }
  .endpoint-path { font-family: ui-monospace, monospace; font-size: .88rem; direction: ltr; unicode-bidi: isolate; overflow-wrap: anywhere; min-width: 0; }
  .endpoint-perm { margin-inline-start: auto; font-size: .72rem; color: var(--ink-faint); font-family: ui-monospace, monospace; direction: ltr; unicode-bidi: isolate; overflow-wrap: anywhere; }
  .endpoint-desc { color: var(--ink-soft); font-size: .9rem; margin-top: .7rem; }
  .code-cols { display: grid; grid-template-columns: 1fr 1fr; gap: .9rem; margin-top: .9rem; }
  @media (max-width: 640px) { .code-cols { grid-template-columns: 1fr; } .endpoint-perm { margin-inline-start: 0; } }
  .code-block { min-width: 0; }
  .code-block.full { grid-column: 1 / -1; }
  .code-label { font-size: .7rem; letter-spacing: .04em; text-transform: uppercase; color: var(--ink-faint); font-family: ui-monospace, monospace; margin-bottom: .35rem; }
  pre {
    margin: 0; background: var(--code-bg); color: var(--code-ink); border-radius: 10px;
    padding: .85rem .95rem; font-size: .78rem; line-height: 1.6; overflow-x: auto;
    direction: ltr; unicode-bidi: isolate; text-align: left;
  }
  pre code { font-family: ui-monospace, 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; }

  .tbl { overflow-x: auto; margin-block: 1rem; }
  table.ref { width: 100%; border-collapse: collapse; margin-block: 0; font-size: .86rem; }
  table.ref th, table.ref td { text-align: right; padding: .55rem .7rem; border-bottom: 1px solid var(--border-soft); vertical-align: top; overflow-wrap: anywhere; }
  table.ref th { color: var(--ink-faint); font-size: .72rem; text-transform: uppercase; letter-spacing: .03em; font-family: ui-monospace, monospace; }
  table.ref td.mono, table.ref code { font-family: ui-monospace, monospace; direction: ltr; unicode-bidi: isolate; overflow-wrap: anywhere; }
  table.ref td.req-yes { color: var(--post); font-size: .78rem; font-weight: 700; }
  table.ref td.req-no { color: var(--ink-faint); font-size: .78rem; }
  .params-label { font-size: .74rem; letter-spacing: .03em; color: var(--ink-faint); text-transform: uppercase; font-family: ui-monospace, monospace; margin-top: 1.1rem; margin-bottom: -.4rem; display: block; }
  @media (max-width: 640px) { table.ref { font-size: .78rem; } }
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
      <li><a href="#ch-13">REST API برای سامانه‌های بیرونی</a></li>
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

      <h3>راهنمای گفتگو: در هر بخش چه بپرسید</h3>
      <p>
        با دستیار دقیقاً مثل یک همکار آگاه از همه‌ی سامانه، به فارسیِ معمولی حرف بزنید — نیازی به عبارت یا اصطلاح خاصی
        نیست. اسم شخص، مشتری، پروژه یا کلیدواژه‌ی موردنظرتان را همان‌جا در جمله بیاورید؛ بازه‌ی زمانی («این ماه»،
        «ماه قبل»، «۷ روز اخیر») را هم خودتان مشخص کنید یا خالی بگذارید تا دستیار پیش‌فرض منطقی را انتخاب کند. هر وقت
        لازم شد می‌توانید با یک سؤال کوتاه‌تر عمیق‌تر بروید («حالا فقط مال رضا رو نشون بده»، «این برای ماه قبل چطور بود؟»).
        هرجا داده‌ها قابل نمودار باشند (روند، مقایسه، رتبه‌بندی)، دستیار علاوه بر پاسخ نوشتاری یک نمودار هم همراهش نشان می‌دهد.
      </p>

      <h3>جست‌وجوی سراسری و هشدارها</h3>
      <div class="tbl"><table class="ref">
        <thead><tr><th>چه بگویید</th><th>چه می‌گیرید</th></tr></thead>
        <tbody>
          <tr><td>«رضا کریمی رو پیدا کن»، «فلان پروژه/قرارداد کجاست»</td><td>جست‌وجوی هم‌زمان در تسک‌ها، پروژه‌ها، معاملات، قراردادها، اسناد، تیکت‌های پشتیبانی، شرکت‌ها و حتی مقادیر داخل پاسخ‌های فرم‌ها - بدون این‌که بخش را مشخص کنید.</td></tr>
          <tr><td>«الان چه هشدارهایی داریم؟»</td><td>فهرست هشدارهای قانون‌محور کل سامانه: تسک‌های عقب‌افتاده، پروژه‌های پرخرج، تیکت‌هایی که از SLA گذشته‌اند، تأخیر مکرر کارمندان، اشغال پایین اقامتگاه.</td></tr>
        </tbody>
      </table></div>

      <h3>هتل‌داری و رزرو</h3>
      <div class="tbl"><table class="ref">
        <thead><tr><th>چه بگویید</th><th>چه می‌گیرید</th></tr></thead>
        <tbody>
          <tr><td>«وضعیت اقامتگاه الان چطوره؟»</td><td>خلاصه‌ی زنده: اشغال/خالی/نیازمند نظافت، چک‌این و چک‌اوت امروز، درآمد این ماه.</td></tr>
          <tr><td>«تا آخر هفته چقدر اتاق خالی داریم؟»</td><td>پیش‌بینی روز‌به‌روز اشغال اتاق‌ها، همراه تعداد ورود و خروج هر روز.</td></tr>
          <tr><td>«رزرو آقای/خانم فلانی رو پیدا کن»، «آخرین رزروها رو نشون بده»</td><td>جست‌وجوی رزرو با نام مهمان، کد رزرو، وضعیت یا بازه‌ی تاریخ.</td></tr>
          <tr><td>«امروز/فردا کیا میان؟» و «کیا دارن میرن؟»</td><td>لیست دقیق ورودی‌ها و خروجی‌های چند روز آینده، با نام مهمان، اتاق و تعداد شب - نه فقط یک عدد.</td></tr>
          <tr><td>«کدوم مهمون از تاریخ خروجش گذشته؟»</td><td>مهمان‌هایی که باید خارج می‌شدند ولی هنوز چک‌اوت نشده‌اند؛ برای پیگیری پذیرش.</td></tr>
          <tr><td>«درآمد این ماه به تفکیک نوع اتاق چقدره؟»</td><td>درآمد، تعداد شب فروخته‌شده و نرخ متوسط هر نوع اتاق.</td></tr>
          <tr><td>«چند درصد رزروها از سایت واسطه میاد؟»</td><td>تعداد، درآمد و کمیسیون بدهکار به تفکیک منبع رزرو (حضوری، تلفنی، وب‌سایت، سایت واسط).</td></tr>
          <tr><td>«نرخ کنسلی رزروهای این ماه چقدره؟»</td><td>تعداد و درصد رزروهایی که کنسل شده‌اند.</td></tr>
        </tbody>
      </table></div>

      <h3>وظایف و پروژه‌ها</h3>
      <div class="tbl"><table class="ref">
        <thead><tr><th>چه بگویید</th><th>چه می‌گیرید</th></tr></thead>
        <tbody>
          <tr><td>«چند تسک عقب‌افتاده داریم؟»</td><td>عدد کلی به‌علاوه‌ی تفکیک همه‌ی تسک‌ها بر اساس وضعیت.</td></tr>
          <tr><td>«کدوم تسک‌ها عقب افتادن و مسئولشون کیه؟»</td><td>لیست دقیق هر تسک با اسم مسئول و میزان تأخیر - نه فقط یک عدد.</td></tr>
          <tr><td>«کارهای باز فلانی رو نشون بده»</td><td>جست‌وجوی تسک با مسئول، وضعیت، اولویت، دپارتمان یا بازه‌ی موعد.</td></tr>
          <tr><td>«بار کاری هرکس چقدره؟»</td><td>تعداد کارهای باز، عقب‌افتاده و تکمیل‌شده‌ی این ماه، به‌ازای هر نفر.</td></tr>
          <tr><td>«وضعیت کلی پروژه‌ها چطوره؟»</td><td>پیشرفت واقعی در برابر برنامه‌ریزی‌شده، و انحراف هزینه/زمان هر پروژه‌ی فعال.</td></tr>
          <tr><td>«پروژه‌ی فلان چه وضعیتی داره؟»</td><td>جزئیات کامل همان یک پروژه: بودجه، هزینه‌ی واقعی، و مسیر بحرانی.</td></tr>
          <tr><td>«کدوم فاز پروژه عقب افتاده؟»</td><td>فازهای مشخصی که از زمان‌بندی خودشان عقب‌اند، نه فقط درصد کلی پروژه.</td></tr>
          <tr><td>«پروژه‌ی فلان چقدر خرج کرده، روی چی؟»</td><td>بودجه، هزینه‌ی مصرف‌شده، باقیمانده، و ریز هزینه‌ها.</td></tr>
          <tr><td>«یک تسک جدید بساز برای فلانی، مهلتش فردا»</td><td>ساخت واقعی تسک در سامانه و تأیید همان لحظه.</td></tr>
        </tbody>
      </table></div>

      <h3>CRM و مشتریان</h3>
      <div class="tbl"><table class="ref">
        <thead><tr><th>چه بگویید</th><th>چه می‌گیرید</th></tr></thead>
        <tbody>
          <tr><td>«وضعیت فروش و پشتیبانی چطوره؟»</td><td>پایپ‌لاین معاملات به تفکیک مرحله، و وضعیت کلی تیکت‌های پشتیبانی.</td></tr>
          <tr><td>«مشتری فلانی چه وضعیتی داره؟»</td><td>نمای کامل همان مشتری: سطح وفاداری، سابقه و ارزش اقامت‌ها، موجودی کیف پول، معامله/تیکت باز، آخرین بازخورد.</td></tr>
          <tr><td>«مشتریان سطح VIP/طلایی رو نشون بده»</td><td>جست‌وجو/فیلتر مشتریان با نام، موبایل یا سطح وفاداری.</td></tr>
          <tr><td>«کدوم تیکت پشتیبانی از مهلتش گذشته؟»</td><td>موضوع تیکت، اسم مشتری و میزان تأخیر از مهلت پاسخ‌گویی.</td></tr>
          <tr><td>«عملکرد فروش هر کارشناس چطوره؟»</td><td>پایپ‌لاین باز و نرخ موفقیت هر کارشناس فروش.</td></tr>
          <tr><td>«مشتری‌ها بیشتر از کجا میان؟»</td><td>تعداد، ارزش و نرخ موفقیت معاملات به تفکیک منبع (حضوری، تلفنی، وب‌سایت، معرفی دوستان و...).</td></tr>
          <tr><td>«مشتریان برتر/پرارزش رو نشون بده»</td><td>رتبه‌بندی مشتریان بر اساس ارزش طول عمرشان - برای تماس قدردانی یا پیشنهاد ویژه.</td></tr>
          <tr><td>«رضایت مشتری‌ها این ماه چطوره؟»</td><td>تفکیک بازخوردها به حامی/خنثی/منتقد و نمره‌ی رضایت کلی.</td></tr>
          <tr><td>«وضعیت کیف پول مشتری‌ها چطوره؟»</td><td>مجموع واریز و برداشت این ماه، و بالاترین موجودی‌های فعلی.</td></tr>
        </tbody>
      </table></div>

      <h3>فرم‌ساز و گردش‌کار</h3>
      <div class="tbl"><table class="ref">
        <thead><tr><th>چه بگویید</th><th>چه می‌گیرید</th></tr></thead>
        <tbody>
          <tr><td>«چند درخواست در انتظار تاییده؟»</td><td>خلاصه‌ی کل فرم‌ساز: در انتظار تایید، تاییدشده/ردشده‌ی این ماه، و تعداد SLA شکسته‌شده.</td></tr>
          <tr><td>«کدوم کارمند فرمی که باید تایید می‌کرد هنوز تایید نکرده؟»</td><td>اسم دقیق مسئول، فرم موردنظر و میزان تأخیرش - نه فقط یک عدد کلی.</td></tr>
          <tr><td>«فلانی کدوم روزهای این ماه چک‌لیست روزانه‌شو پر نکرده؟»</td><td>روزهای ثبت‌نشده‌ی یک فرم تکرارشونده/روزانه برای همان کارمند.</td></tr>
          <tr><td>«کدوم مرحله‌ی گردش‌کار بیشترین معطلی رو داره؟»</td><td>میانگین زمان پاسخ هر مرحله، کندترین اول.</td></tr>
          <tr><td>«عملکرد تاییدکننده‌ها چطوره؟»</td><td>نرخ رد/تایید و سرعت پاسخ هر تاییدکننده.</td></tr>
          <tr><td>«دلایل رد فلان فرم چی بوده؟»</td><td>متن خام نظرات ردشده، آماده برای جمع‌بندی شما.</td></tr>
          <tr><td>«این ماه نسبت به ماه قبل چطور بوده؟»</td><td>مقایسه‌ی تعداد ثبت/تایید/رد بین این ماه و ماه قبل.</td></tr>
          <tr><td>«یک فرم/گردش‌کار تازه برام طراحی کن برای ...»</td><td>طراحی پیش‌نویس واقعی فرم یا گردش‌کار تأیید - همیشه غیرفعال ذخیره می‌شود تا یک مدیر از پنل بررسی و فعالش کند.</td></tr>
          <tr><td>«یک فیلد تازه به فرم فلان اضافه کن»</td><td>افزودن فیلد به فرم موجود، بدون حذف یا تغییر فیلدهای قبلی و پاسخ‌های ثبت‌شده.</td></tr>
        </tbody>
      </table></div>

      <h3>حضور و غیاب</h3>
      <div class="tbl"><table class="ref">
        <thead><tr><th>چه بگویید</th><th>چه می‌گیرید</th></tr></thead>
        <tbody>
          <tr><td>«وضعیت حضور این ماه چطوره؟»</td><td>تعداد به‌موقع/تأخیر/غیبت این ماه، و پرتأخیرترین افراد.</td></tr>
          <tr><td>«امروز کیا هنوز ورود نزدن؟»</td><td>لیست زنده‌ی کسانی که امروز هنوز چک‌این نکرده‌اند - بدون نیاز به رسیدن پایان روز.</td></tr>
          <tr><td>«وضعیت حضور هرکس این ماه چطوره؟»</td><td>تفکیک کامل به‌ازای هر نفر: به‌موقع/تأخیر/غیبت، دقایق تأخیر، ساعت کارکرد، نرخ حضور.</td></tr>
          <tr><td>«کدوم دپارتمان بدترین وضعیت حضور رو داره؟»</td><td>مقایسه‌ی نرخ به‌موقع‌بودن و میانگین تأخیر بین دپارتمان‌ها.</td></tr>
          <tr><td>«چه کسی چک‌اوت نزده؟»</td><td>ثبت‌های ورودی که هنوز خروج ثبت نشده، در هر تاریخی.</td></tr>
          <tr><td>«کی کم‌کاری یا اضافه‌کاری داشته؟»</td><td>ساعت کارکرد واقعی هرکس در برابر ساعت شیفتش.</td></tr>
          <tr><td>«وضعیت حضور این ماه نسبت به ماه قبل چطور شده؟»</td><td>مقایسه‌ی روند، کل سازمان یا یک دپارتمان مشخص.</td></tr>
          <tr><td>«ورود با دستگاه عجیب داشتیم؟»</td><td>هشدار برای بررسی توسط منابع انسانی - کارمندی که با دستگاه متفاوت از دفعه‌ی قبل وارد شده (نه مدرک قطعی).</td></tr>
        </tbody>
      </table></div>

      <h3>مرکز سئو</h3>
      <div class="tbl"><table class="ref">
        <thead><tr><th>چه بگویید</th><th>چه می‌گیرید</th></tr></thead>
        <tbody>
          <tr><td>«وضعیت سئو چطوره؟»</td><td>نقطه‌ی شروع: اتصال به گوگل، آخرین کلیک/نمایش/جایگاه، تعداد فرصت باز، امتیاز آخرین ممیزی فنی.</td></tr>
          <tr><td>«روند ترافیک سایت این مدت چطور بوده؟»</td><td>کلیک و نمایش روزانه، به‌علاوه‌ی درصد تغییر نسبت به دوره‌ی قبل.</td></tr>
          <tr><td>«کدوم عبارت‌ها/صفحه‌ها بیشترین کلیک رو میارن؟»</td><td>رتبه‌بندی عبارت‌های جستجو و صفحات پراکلیک، با جایگاه و نرخ کلیک هرکدام.</td></tr>
          <tr><td>«کلیدواژه‌هامون چه وضعیتی دارن؟»</td><td>جایگاه فعلی، روند، ارزش تجاری و ریسک کنیبالیزیشن هر کلیدواژه‌ی ردیابی‌شده.</td></tr>
          <tr><td>«رتبه‌ی فلان کلیدواژه چطور بوده؟»</td><td>تاریخچه‌ی روز‌به‌روز جایگاه همان کلیدواژه در گوگل.</td></tr>
          <tr><td>«چه فرصت‌های سئویی داریم؟»</td><td>فرصت‌های کشف‌شده‌ی هنوز حل‌نشده، با اولویت و اقدام پیشنهادی هرکدام.</td></tr>
          <tr><td>«آیا اقداماتی که روی سئو انجام دادیم جواب داده؟»</td><td>نتیجه‌ی واقعی فرصت‌های حل‌شده: بهتر شده، بدون تغییر مانده یا بدتر شده.</td></tr>
          <tr><td>«وضعیت فنی سایت چطوره؟»</td><td>امتیاز کلی، Core Web Vitals، و آدرس دقیق صفحاتی با مشکل فنی بحرانی.</td></tr>
          <tr><td>«تقویم محتوای سئو چه وضعیتی داره؟»</td><td>آیتم‌های محتوا به تفکیک وضعیت، و موارد عقب‌افتاده با اسم مسئول.</td></tr>
          <tr><td>«چه کلیدواژه‌هایی اخیراً بهتر/بدتر شدن؟»</td><td>تغییر واقعی جایگاه کلیدواژه‌ها در یک بازه، بهبودها و افت‌ها جدا از هم.</td></tr>
        </tbody>
      </table></div>

      <div class="callout">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c5.5-1.3 8-5 8-10V6l-8-3-8 3v6c0 5 2.5 8.7 8 10Z"/><path d="m9 12 2 2 4-4"/></svg>
        <div>
          <span class="label">سه نکته‌ی مهم</span>
          <p>
            دستیار فقط بر اساس داده‌های واقعیِ همین لحظه‌ی سامانه جواب می‌دهد - هیچ‌وقت عددی را حدس نمی‌زند.
            هر اقدام واقعی که از او بخواهید (طراحی فرم یا گردش‌کار تازه، افزودن فیلد) همیشه به‌صورت غیرفعال/پیش‌نویس
            ذخیره می‌شود تا یک مدیر از پنل بررسی و تاییدش کند. و دسترسی هرکس به هر گزارش یا اقدام، دقیقاً بر اساس
            مجوزهایی است که برایش در پنل تعریف شده - دستیار این مرزها را هیچ‌وقت دور نمی‌زند.
          </p>
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
        <div class="feature-card"><div class="name">رویدادها</div><div class="desc">ثبت رویدادهای داخلی سازمان با یادآوری‌های دلخواه و چندگانه (مثلاً ۱ هفته، ۲۴ ساعت و ۱ ساعت قبل)، از طریق پوش/پیامک/ایمیل.</div></div>
      </div>
      <div class="howto">
        <div class="howto-label">مراحل استفاده</div>
        <div class="shots">
          <figure class="shot">
            <img src="/system-guide-assets/shots/26-messenger.png" alt="پیام‌رسان داخلی با پیام صوتی">
            <figcaption><span class="step-n">۱</span> گفتگو با هر همکار؛ برای ارسال پیام صوتی روی آیکون میکروفون بزنید و برای پیوست فایل از گیره‌ی کنار آن استفاده کنید — همان‌طور که در این گفتگوی واقعی، هم فایل و هم پیام صوتی پخش‌شدنی دیده می‌شود.</figcaption>
          </figure>
          <figure class="shot">
            <img src="/system-guide-assets/shots/34-event-create.png" alt="ایجاد رویداد با یادآوری‌های چندگانه">
            <figcaption><span class="step-n">۲</span> از «رویدادها» یک رویداد جدید بسازید: عنوان، توضیحات، تاریخ و ساعت (شمسی)، و شرکت‌کنندگانی که یادآوری برایشان ارسال می‌شود. با دکمه‌ی «افزودن یادآوری‌های استاندارد» سه یادآوری آماده (۱ هفته، ۲۴ ساعت و ۱ ساعت قبل) یک‌جا اضافه می‌شود؛ برای هرکدام می‌توانید کانال ارسال (پوش، پیامک، ایمیل) را جداگانه روشن یا خاموش کنید، یا خودتان یادآوری دلخواه با هر بازه‌ی زمانی اضافه کنید.</figcaption>
          </figure>
          <figure class="shot">
            <img src="/system-guide-assets/shots/35-events-list.png" alt="فهرست رویدادها">
            <figcaption><span class="step-n">۳</span> فهرست رویدادها: تاریخ و ساعت، شرکت‌کنندگان و تعداد یادآوری‌های هرکدام در یک نگاه؛ یادآوری‌ها سر وقت خودشان، از طریق کانال‌های انتخاب‌شده، برای شرکت‌کنندگان ارسال می‌شوند.</figcaption>
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

  <!-- ============ 13 — REST API FOR EXTERNAL SYSTEMS ============ -->
  <section class="chapter" id="ch-13">
    <div class="chapter-head">
      <div class="chapter-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 3 14h8l-1 8 10-12h-8l1-8Z"/></svg>
      </div>
      <div>
        <span class="chapter-num">فصل ۱۳</span>
        <h2>REST API برای سامانه‌های بیرونی</h2>
      </div>
    </div>
    <div class="chapter-body">
      <p class="lede">
        مجموعه‌ای از APIهای REST نسخه‌بندی‌شده که سامانه‌های بیرونی (وب‌سایت رزرو، گیت‌وی پرداخت، اسکنر گیت مستقل،
        فرم‌های آنلاین و ابزارهای شخص‌ثالث) را به هسته‌ی هتلداری، فروش بلیط، CRM، فرم‌ساز و مدیریت اسناد سامانه متصل می‌کند.
        هر درخواست JSON روی HTTPS است، با همان قرارداد خطا و همان مدل احراز هویت در همه‌ی بخش‌ها.
      </p>
      <ul class="subtoc">
        <li><a href="#ch-13-auth">احراز هویت، خطاها و Idempotency</a></li>
        <li><a href="#ch-13-quickstart">شروع سریع</a></li>
        <li><a href="#ch-13-ticketing">فروش بلیط و گیشه (POS)</a></li>
        <li><a href="#ch-13-hospitality">اقامتی و هتلداری</a></li>
        <li><a href="#ch-13-crm">CRM و سرنخ‌ها</a></li>
        <li><a href="#ch-13-forms">فرم‌ساز</a></li>
        <li><a href="#ch-13-documents">مدیریت اسناد</a></li>
        <li><a href="#ch-13-webhooks">وب‌هوک‌های خروجی</a></li>
        <li><a href="#ch-13-errors">مرجع کدهای خطا</a></li>
        <li><a href="#ch-13-permissions">مرجع کامل مجوزها</a></li>
      </ul>

      <h3 id="ch-13-auth">احراز هویت، خطاها و Idempotency</h3>
      <p>
        هر اکانت API (<span class="mono">ExternalApiClient</span>) یک جفت <span class="mono">api_key</span>/
        <span class="mono">api_secret</span> دارد. این‌ها هرگز جای نشست کاربر پنل را نمی‌گیرند - مخصوص سامانه‌های
        بیرونی‌اند، نه پرسنل. آدرس پایه‌ی همه‌ی مسیرهای این بخش:
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

      <p style="margin-top:1rem"><b>گام‌های احراز هویت (چه اتفاقی روی هر درخواست می‌افتد):</b></p>
      <ol class="steps">
        <li><b>۱.</b> هدرهای <span class="mono">X-API-Key</span> و <span class="mono">X-API-Secret</span> بررسی می‌شوند؛ نبودشان <span class="mono">401 UNAUTHORIZED</span> می‌دهد.</li>
        <li><b>۲.</b> اکانتی با همان <span class="mono">api_key</span> پیدا می‌شود؛ اگر نبود، غیرفعال بود، یا رمز اشتباه بود، همان <span class="mono">401 UNAUTHORIZED</span> برمی‌گردد (عمداً بدون افشای اینکه کدام‌یک اشتباه است).</li>
        <li><b>۳.</b> سقف نرخ درخواست آن اکانت بررسی می‌شود (بخش بعد را ببینید).</li>
        <li><b>۴.</b> اکانت مجاز روی درخواست قرار می‌گیرد و کنترلر مجوز عملیات مشخص (مثل <span class="mono">ticket.issue</span>) را جداگانه بررسی می‌کند؛ نبود آن مجوز <span class="mono">403 FORBIDDEN</span> می‌دهد.</li>
      </ol>

      <p style="margin-top:1.4rem"><b>محدودیت نرخ درخواست (Rate Limiting):</b></p>
      <p>
        هر اکانت یک سقف <span class="mono">rate_limit_per_minute</span> دارد (پیش‌فرض ۶۰، از پنل «اکانت‌های API خارجی» قابل تغییر است).
        شمارش به‌صورت یک پنجره‌ی ۶۰ ثانیه‌ای، جداگانه برای هر اکانت (بر اساس شناسه‌ی اکانت، نه IP) انجام می‌شود. عبور از سقف،
        بدون نیاز به هیچ هدر خاصی، پاسخ زیر را می‌دهد (در نسخه‌ی فعلی هدرهای <span class="mono">X-RateLimit-*</span> برگردانده نمی‌شوند):
      </p>
      <pre><code>HTTP/1.1 429 Too Many Requests
{ "error": { "code": "RATE_LIMITED", "message": "تعداد درخواست‌ها بیش از حد مجاز است." } }</code></pre>

      <div class="callout">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
        <div>
          <span class="label">Idempotency-Key</span>
          <p>
            هر متد POST‌ی که چیزی می‌سازد (بلیط، رزرو، سرنخ، سند، بازپرداخت، ثبت فرم) به هدر
            <span class="mono">Idempotency-Key</span> نیاز دارد - نبودش <span class="mono">400 INVALID_REQUEST</span> می‌دهد.
            هر رشته‌ی یکتا (پیشنهاد: یک UUID v4 که همان سمت شما، پیش از فرستادن درخواست، ساخته می‌شود) قابل قبول است.
          </p>
        </div>
      </div>
      <div class="tbl"><table class="ref">
        <thead><tr><th>وضعیت درخواست تکراری</th><th>پاسخ</th></tr></thead>
        <tbody>
          <tr><td>همان کلید + همان بدنه‌ی دقیق درخواست</td><td>همان پاسخ اولین بار (همان کد وضعیت و همان بدنه) دوباره و بدون ساخت رکورد جدید برگردانده می‌شود.</td></tr>
          <tr><td>همان کلید + بدنه‌ی متفاوت</td><td><span class="mono">409 CONFLICT</span> - این کلید قبلاً با محتوای دیگری استفاده شده است.</td></tr>
          <tr><td>دو درخواست هم‌زمان با کلید و بدنه‌ی یکسان</td><td>هر دو یک رکورد می‌سازند تا زمانی که یکی commit شود؛ دیگری به‌طور خودکار پاسخ همان رکورد را برمی‌گرداند (بدون رکورد تکراری).</td></tr>
          <tr><td>کلید تازه (هنوز دیده نشده)</td><td>درخواست به‌طور عادی پردازش و رکورد ساخته می‌شود.</td></tr>
        </tbody>
      </table></div>
      <p>کلیدها به‌صورت دائمی نگه‌داشته می‌شوند - از یک کلید فقط یک‌بار، برای یک عملیات مشخص، استفاده کنید.</p>

      <p style="margin-top:1.4rem">
        هر عملیات به یک مجوز مشخص روی اکانت نیاز دارد (فهرست کامل در <a href="#ch-13-permissions">بخش مرجع مجوزها</a>)؛ نبود آن مجوز
        <span class="mono">403 FORBIDDEN</span> می‌دهد. هر رکورد ساخته‌شده از طریق API فقط برای همان اکانتی که آن را ساخته قابل
        خواندن است - رکوردهای ساخته‌شده در پنل مدیریت، یا توسط اکانت دیگری، هرگز از این API دیده نمی‌شوند
        (پاسخ <span class="mono">404</span>، نه ۴۰۳، تا وجود یا عدم‌وجود رکورد فاش نشود).
      </p>

      <h3 id="ch-13-quickstart">شروع سریع - از صفر تا اولین درخواست موفق</h3>
      <ol class="steps">
        <li><b>۱. ساخت اکانت API.</b> در پنل مدیریت، از منوی کاربری وارد «اکانت‌های API خارجی» شوید (زیرساخت سازمانی) و یک اکانت جدید بسازید: نام سامانه، عملیات‌های مجاز، سقف نرخ درخواست، و در صورت نیاز کاربر «ثبت به نام» (برای ثبت فرم). رمز فقط همان یک لحظه نمایش داده می‌شود - همان‌جا کپی و در جای امن ذخیره کنید.</li>
        <li><b>۲. یک درخواست تستیِ ساده (GET) بزنید</b> تا احراز هویت را تأیید کنید:
          <pre><code>curl -s https://your-domain.example/api/v1/external/products \
  -H "X-API-Key: pk_xxxxxxxxxxxxxxxx" \
  -H "X-API-Secret: your-plain-secret"</code></pre>
          پاسخ موفق یک آرایه‌ی <span class="mono">data</span> برمی‌گرداند (حتی اگر خالی باشد).
        </li>
        <li><b>۳. یک POST را با Idempotency-Key امتحان کنید</b> - مثلاً ثبت یک سرنخ آزمایشی:
          <pre><code>curl -s -X POST https://your-domain.example/api/v1/external/deals \
  -H "X-API-Key: pk_xxxxxxxxxxxxxxxx" \
  -H "X-API-Secret: your-plain-secret" \
  -H "Idempotency-Key: $(uuidgen)" \
  -H "Content-Type: application/json" \
  -d '{"title": "تست اتصال"}'</code></pre>
        </li>
        <li><b>۴. همان درخواست را دوباره با همان Idempotency-Key بفرستید</b> و مطمئن شوید دقیقاً همان پاسخ (و نه یک سرنخ تکراری) برمی‌گردد.</li>
        <li><b>۵. سراغ endpointهای دامنه‌ی مورد نیازتان بروید</b> و مطمئن شوید عملیات مربوطه در «عملیات‌های مجاز» اکانتتان فعال است؛ در غیر این صورت <span class="mono">403 FORBIDDEN</span> می‌گیرید.</li>
      </ol>
      <div class="callout">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
        <div>
          <span class="label">محیط آزمایشی جداگانه‌ای وجود ندارد</span>
          <p>این API محیط sandbox مجزا ندارد؛ هر درخواست روی همان دیتابیس واقعی اجرا می‌شود. برای تست، یک اکانت API جداگانه با فقط عملیات‌های لازم بسازید و رکوردهای تستی را بعداً از پنل پاک کنید.</p>
        </div>
      </div>

      <h3 id="ch-13-ticketing">فروش بلیط و گیشه (POS)</h3>
      <p>صدور بلیط، اعتبارسنجی کد تخفیف، بازپرداخت و اسکن مستقل گیت - برای یک گیشه‌ی فروش بیرونی یا اسکنر آفلاین.</p>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-get">GET</span>
          <span class="endpoint-path">/products</span>
          <span class="endpoint-perm">product.read</span>
        </div>
        <div class="endpoint-desc">فهرست محصولات (رویداد/تور) فعال قابل فروش برای این اکانت.</div>
        <div class="code-block full">
          <div class="code-label">curl</div>
          <pre><code>curl -s https://your-domain.example/api/v1/external/products \
  -H "X-API-Key: $KEY" -H "X-API-Secret: $SECRET"</code></pre>
        </div>
        <span class="params-label">پاسخ · Response fields</span>
        <div class="tbl"><table class="ref">
          <thead><tr><th>فیلد</th><th>نوع</th><th>توضیح</th></tr></thead>
          <tbody>
            <tr><td class="mono">data[].id</td><td class="mono">int</td><td>شناسه‌ی محصول.</td></tr>
            <tr><td class="mono">data[].code</td><td class="mono">string</td><td>کد یکتای محصول.</td></tr>
            <tr><td class="mono">data[].name</td><td class="mono">string</td><td>نام محصول.</td></tr>
            <tr><td class="mono">data[].status</td><td class="mono">string</td><td><span class="mono">active</span> یا <span class="mono">inactive</span>.</td></tr>
            <tr><td class="mono">data[].variants[]</td><td class="mono">array</td><td>انواع بلیت فعال این محصول: <span class="mono">id</span>, <span class="mono">code</span>, <span class="mono">name</span>.</td></tr>
          </tbody>
        </table></div>
      </div>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-get">GET</span>
          <span class="endpoint-path">/ticket-variants?product_id=</span>
          <span class="endpoint-perm">ticket_variant.read</span>
        </div>
        <div class="endpoint-desc">فهرست انواع بلیت فعال، با فیلتر اختیاری روی محصول.</div>
        <span class="params-label">پارامترهای Query</span>
        <div class="tbl"><table class="ref">
          <thead><tr><th>پارامتر</th><th>نوع</th><th>الزامی</th><th>توضیح</th></tr></thead>
          <tbody>
            <tr><td class="mono">product_id</td><td class="mono">int</td><td class="req-no">خیر</td><td>فقط انواع بلیت همین محصول.</td></tr>
          </tbody>
        </table></div>
        <span class="params-label">پاسخ · Response fields</span>
        <div class="tbl"><table class="ref">
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
        </table></div>
      </div>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-post">POST</span>
          <span class="endpoint-path">/tickets</span>
          <span class="endpoint-perm">ticket.issue</span>
        </div>
        <div class="endpoint-desc">صدور یک یا چند بلیط از یک نوع بلیت، در یک سفارش. نیازمند <span class="mono">Idempotency-Key</span>.</div>
        <span class="params-label">بدنه‌ی درخواست</span>
        <div class="tbl"><table class="ref">
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
        </table></div>
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
  "final_amount": 360000,
  "currency": "IRR",
  "tickets": [
    { "ticket_id": 901,
      "ticket_number": "TKT-260927-000123",
      "qr_payload": "...",
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
        <div class="endpoint-desc">جزئیات یک بلیط با شماره‌ی آن - فقط اگر این اکانت آن را صادر کرده باشد. پاسخ شامل <span class="mono">ticket_number</span>، <span class="mono">qr_payload</span> (مقداری که باید عیناً در QR کد‌گذاری شود)، بازه‌ی اعتبار و تعداد مصرف باقی‌مانده است.</div>
      </div>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-post">POST</span>
          <span class="endpoint-path">/tickets/{ticket_number}/reprint</span>
          <span class="endpoint-perm">ticket.reprint</span>
        </div>
        <div class="endpoint-desc">ثبت یک لاگ چاپ مجدد و بازگرداندن همان بلیط/QR - هرگز بلیط جدید نمی‌سازد و اعتبار/مصرف را تغییر نمی‌دهد.</div>
      </div>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-post">POST</span>
          <span class="endpoint-path">/tickets/{ticket_number}/refund</span>
          <span class="endpoint-perm">ticket.refund</span>
        </div>
        <div class="endpoint-desc">بازپرداخت یک بلیط (وضعیت آن Refunded می‌شود و دیگر از گیت رد نمی‌شود). نیازمند <span class="mono">Idempotency-Key</span>؛ بلیط قبلاً لغو یا مسترد‌شده <span class="mono">422 VALIDATION_ERROR</span> می‌دهد.</div>
        <span class="params-label">بدنه‌ی درخواست</span>
        <div class="tbl"><table class="ref">
          <thead><tr><th>فیلد</th><th>نوع</th><th>الزامی</th><th>توضیح</th></tr></thead>
          <tbody>
            <tr><td class="mono">reason</td><td class="mono">string</td><td class="req-no">خیر</td><td>حداکثر ۲۵۵ کاراکتر.</td></tr>
            <tr><td class="mono">reference</td><td class="mono">string</td><td class="req-no">خیر</td><td>حداکثر ۱۰۰ کاراکتر - مثلاً شماره‌ی پیگیری بانکی.</td></tr>
          </tbody>
        </table></div>
      </div>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-post">POST</span>
          <span class="endpoint-path">/discount-codes/validate</span>
          <span class="endpoint-perm">discount_code.validate</span>
        </div>
        <div class="endpoint-desc">پیش‌بررسی یک کد تخفیف روی یک نوع بلیط، بدون مصرف واقعی آن (بدون Idempotency-Key، چون هیچ رکوردی نمی‌سازد).</div>
        <div class="code-cols">
          <div class="code-block">
            <div class="code-label">Request</div>
            <pre><code>{ "code": "SAVE10", "ticket_variant_id": 3 }</code></pre>
          </div>
          <div class="code-block">
            <div class="code-label">Response · 200</div>
            <pre><code>{ "valid": true, "subtotal": 200000,
  "discount_amount": 20000, "final_amount": 180000 }</code></pre>
          </div>
        </div>
      </div>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-post">POST</span>
          <span class="endpoint-path">/gates/{gate_code}/scan</span>
          <span class="endpoint-perm">gate.scan</span>
        </div>
        <div class="endpoint-desc">اعتبارسنجی و مصرف یک بلیط در یک دروازه‌ی مشخص - برای اسکنرهای مستقل که کاربر پرسنلی وارد نشده‌اند. همیشه <span class="mono">200</span> برمی‌گرداند؛ نتیجه در فیلد <span class="mono">result</span> (<span class="mono">success</span> یا <span class="mono">rejected</span>) است.</div>
        <div class="code-cols">
          <div class="code-block">
            <div class="code-label">Request</div>
            <pre><code>{ "qr_token": "..." }</code></pre>
          </div>
          <div class="code-block">
            <div class="code-label">Response · 200</div>
            <pre><code>{ "result": "success", "reason": null,
  "ticket_number": "TKT-260927-000123",
  "usage_number": 1 }</code></pre>
          </div>
        </div>
      </div>

      <h3 id="ch-13-hospitality">اقامتی و هتلداری</h3>
      <p>استعلام موجودی اتاق و ثبت رزرو - برای موتور رزرو آنلاین یا یک OTA بیرونی.</p>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-get">GET</span>
          <span class="endpoint-path">/rooms/availability?check_in=&check_out=&room_type_id=</span>
          <span class="endpoint-perm">room.read</span>
        </div>
        <div class="endpoint-desc">فهرست اتاق‌های فعال و آزاد در بازه‌ی تاریخی داده‌شده.</div>
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
            <pre><code>{ "data": [
  { "room_id": 14, "name": "اتاق ۱۰۱",
    "room_type": {"id": 2, "name": "دبل"},
    "price_for_stay": 4500000 } ] }</code></pre>
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
        <div class="tbl"><table class="ref">
          <thead><tr><th>فیلد</th><th>نوع</th><th>الزامی</th><th>توضیح</th></tr></thead>
          <tbody>
            <tr><td class="mono">room_id</td><td class="mono">int</td><td class="req-yes">بله</td><td>—</td></tr>
            <tr><td class="mono">check_in_date / check_out_date</td><td class="mono">date</td><td class="req-yes">بله</td><td>خروج باید بعد از ورود باشد.</td></tr>
            <tr><td class="mono">adults_count / children_count</td><td class="mono">int</td><td class="req-no">خیر</td><td>پیش‌فرض ۱ و ۰.</td></tr>
            <tr><td class="mono">sales_channel_id</td><td class="mono">int</td><td class="req-no">خیر</td><td>در صورت ارسال، منبع رزرو <span class="mono">ota</span> ثبت می‌شود.</td></tr>
            <tr><td class="mono">notes</td><td class="mono">string</td><td class="req-no">خیر</td><td>حداکثر ۱۰۰۰ کاراکتر.</td></tr>
            <tr><td class="mono">guest.name / guest.mobile</td><td class="mono">string</td><td class="req-yes">بله</td><td>موبایل کلید یکتاسازی مهمان است.</td></tr>
            <tr><td class="mono">guest.national_code / guest.email</td><td class="mono">string</td><td class="req-no">خیر</td><td>—</td></tr>
          </tbody>
        </table></div>
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
        <div class="endpoint-desc">جزئیات یک رزرو با کد مرجع آن - فقط اگر همین اکانت آن را ساخته باشد.</div>
      </div>

      <h3 id="ch-13-crm">CRM و سرنخ‌ها</h3>
      <p>ثبت سرنخ‌های ورودی از سایت، لندینگ تبلیغاتی یا هر فرم بیرونی، مستقیم در همان قیف فروش داخلی.</p>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-post">POST</span>
          <span class="endpoint-path">/deals</span>
          <span class="endpoint-perm">deal.create</span>
        </div>
        <div class="endpoint-desc">ثبت یک سرنخ جدید (همیشه در مرحله‌ی <span class="mono">new_lead</span> شروع می‌شود)، با ساخت خودکار مشتری در صورت نبودش. نیازمند <span class="mono">Idempotency-Key</span>.</div>
        <span class="params-label">بدنه‌ی درخواست</span>
        <div class="tbl"><table class="ref">
          <thead><tr><th>فیلد</th><th>نوع</th><th>الزامی</th><th>توضیح</th></tr></thead>
          <tbody>
            <tr><td class="mono">title</td><td class="mono">string</td><td class="req-yes">بله</td><td>حداکثر ۲۵۵ کاراکتر.</td></tr>
            <tr><td class="mono">value</td><td class="mono">int</td><td class="req-no">خیر</td><td>ارزش تخمینی معامله به ریال.</td></tr>
            <tr><td class="mono">source</td><td class="mono">string</td><td class="req-no">خیر (پیش‌فرض online)</td><td>یکی از: <span class="mono">walk_in</span>, <span class="mono">phone</span>, <span class="mono">online</span>, <span class="mono">referral</span>, <span class="mono">social_media</span>, <span class="mono">other</span>.</td></tr>
            <tr><td class="mono">expected_close_date / notes</td><td class="mono">date / string</td><td class="req-no">خیر</td><td>notes حداکثر ۲۰۰۰ کاراکتر.</td></tr>
            <tr><td class="mono">guest.name / guest.mobile</td><td class="mono">string</td><td class="req-no">اگر guest ارسال شود بله</td><td>—</td></tr>
          </tbody>
        </table></div>
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
  "value": 5000000
}</code></pre>
          </div>
        </div>
        <p style="margin-top:.6rem">مراحل ممکن برای <span class="mono">stage</span> (فقط از پنل تغییر می‌کند): <span class="mono">new_lead</span>, <span class="mono">needs_assessment</span>, <span class="mono">proposal_sent</span>, <span class="mono">negotiation</span>, <span class="mono">won</span>, <span class="mono">lost</span>.</p>
      </div>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-get">GET</span>
          <span class="endpoint-path">/deals/{id}</span>
          <span class="endpoint-perm">deal.read</span>
        </div>
        <div class="endpoint-desc">جزئیات یک سرنخ با شناسه‌ی آن - فقط اگر همین اکانت آن را ساخته باشد.</div>
      </div>

      <h3 id="ch-13-forms">فرم‌ساز (ثبت عمومی فرم)</h3>
      <p>درج مستقیم پاسخ یک فرم داخلی از یک وب‌سایت یا اپلیکیشن بیرونی - دقیقاً از همان مسیر تأیید/گردش‌کاری که یک ثبت داخلی طی می‌کند.</p>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-post">POST</span>
          <span class="endpoint-path">/form-templates/{form_template_id}/submissions</span>
          <span class="endpoint-perm">form_submission.create</span>
        </div>
        <div class="endpoint-desc">ثبت یک پاسخ جدید برای یک قالب فعال. فیلدهای نوع فایل/امضا/GPS/جدول از این مسیر پشتیبانی نمی‌شوند.</div>
        <span class="params-label">بدنه‌ی درخواست</span>
        <div class="tbl"><table class="ref">
          <thead><tr><th>فیلد</th><th>نوع</th><th>الزامی</th><th>توضیح</th></tr></thead>
          <tbody>
            <tr><td class="mono">values</td><td class="mono">object</td><td class="req-no">خیر</td><td>نگاشت <span class="mono">field_key</span> ← مقدار، برای هر فیلد قالب.</td></tr>
          </tbody>
        </table></div>
        <div class="code-cols">
          <div class="code-block">
            <div class="code-label">curl</div>
            <pre><code>curl -s -X POST .../form-templates/7/submissions \
  -H "X-API-Key: $KEY" -H "X-API-Secret: $SECRET" \
  -H "Idempotency-Key: $(uuidgen)" \
  -H "Content-Type: application/json" \
  -d '{"values": {"full_name": "زهرا محمدی"}}'</code></pre>
          </div>
          <div class="code-block">
            <div class="code-label">Response · 201</div>
            <pre><code>{ "submission_id": 77,
  "reference_code": "FRM-...",
  "status": "approved" }</code></pre>
          </div>
        </div>
      </div>

      <div class="callout">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
        <div>
          <span class="label">پیش‌نیاز اکانت</span>
          <p>اکانتی که این مسیر را صدا می‌زند باید یک «کاربر ثبت به نام» (<span class="mono">created_by</span>) تنظیم‌شده داشته باشد - در پنل، هنگام ساخت اکانت API. بدون آن، پاسخ <span class="mono">422 CONFIGURATION_ERROR</span> برمی‌گردد.</p>
        </div>
      </div>

      <h3 id="ch-13-documents">مدیریت اسناد</h3>
      <p>بارگذاری و دریافت فایل، مستقیم در سیستم مدیریت اسناد پنل (پوشه‌ها، تاریخچه‌ی نسخه‌ها و سطح دسترسی یکسان).</p>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-post">POST</span>
          <span class="endpoint-path">/documents</span>
          <span class="endpoint-perm">document.upload</span>
        </div>
        <div class="endpoint-desc">بارگذاری یک فایل (<span class="mono">multipart/form-data</span>). نیازمند <span class="mono">Idempotency-Key</span>.</div>
        <span class="params-label">فیلدهای فرم (multipart)</span>
        <div class="tbl"><table class="ref">
          <thead><tr><th>فیلد</th><th>نوع</th><th>الزامی</th><th>توضیح</th></tr></thead>
          <tbody>
            <tr><td class="mono">title</td><td class="mono">string</td><td class="req-yes">بله</td><td>حداکثر ۲۵۵ کاراکتر.</td></tr>
            <tr><td class="mono">file</td><td class="mono">file</td><td class="req-yes">بله</td><td>حداکثر حجم ۱۰٬۲۴۰ کیلوبایت.</td></tr>
            <tr><td class="mono">access_level</td><td class="mono">string</td><td class="req-no">خیر (پیش‌فرض restricted)</td><td><span class="mono">public_internal</span>, <span class="mono">department_only</span>, <span class="mono">restricted</span>.</td></tr>
            <tr><td class="mono">department_id</td><td class="mono">int</td><td class="req-no">فقط با department_only بله</td><td>—</td></tr>
          </tbody>
        </table></div>
        <div class="code-block full">
          <div class="code-label">curl</div>
          <pre><code>curl -s -X POST .../documents \
  -H "X-API-Key: $KEY" -H "X-API-Secret: $SECRET" \
  -H "Idempotency-Key: $(uuidgen)" \
  -F "title=قرارداد اجاره اتاق" \
  -F "file=@/path/to/contract.pdf"</code></pre>
        </div>
      </div>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-get">GET</span>
          <span class="endpoint-path">/documents/{id}/download</span>
          <span class="endpoint-perm">document.read</span>
        </div>
        <div class="endpoint-desc">دریافت مستقیم فایل جاری یک سند - فقط اگر همین اکانت آن را بارگذاری کرده باشد.</div>
      </div>

      <h3 id="ch-13-webhooks">وب‌هوک‌های خروجی</h3>
      <p>اطلاع‌رسانی لحظه‌ای رویدادهای کلیدی (اسکن موفق/ناموفق بلیط، ثبت رزرو، تغییر مرحله‌ی سرنخ) به سامانه‌ی بیرونی، به‌جای Polling.</p>

      <div class="endpoint">
        <div class="endpoint-head">
          <span class="method method-post">POST</span>
          <span class="endpoint-path">/webhooks</span>
          <span class="endpoint-perm">webhook.manage</span>
        </div>
        <div class="endpoint-desc">ثبت یک اشتراک جدید. <span class="mono">signing_secret</span> فقط همین یک‌بار برگردانده می‌شود.</div>
        <div class="code-cols">
          <div class="code-block">
            <div class="code-label">Request</div>
            <pre><code>{ "event_type": "booking.created",
  "target_url": "https://example.com/hooks/bookings" }</code></pre>
          </div>
          <div class="code-block">
            <div class="code-label">Response · 201</div>
            <pre><code>{ "webhook_subscription_id": 4,
  "event_type": "booking.created",
  "signing_secret": "ONLY-SHOWN-ONCE..." }</code></pre>
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

      <p style="margin-top:1.4rem"><b>رویدادهای پشتیبانی‌شده:</b></p>
      <div class="tbl"><table class="ref">
        <thead><tr><th>event_type</th><th>زمان ارسال</th></tr></thead>
        <tbody>
          <tr><td class="mono"><code>ticket.scanned</code></td><td>اسکن موفق یک بلیط در گیت</td></tr>
          <tr><td class="mono"><code>ticket.rejected</code></td><td>رد شدن یک اسکن در گیت</td></tr>
          <tr><td class="mono"><code>booking.created</code></td><td>ثبت یک رزرو جدید متعلق به همین اکانت</td></tr>
          <tr><td class="mono"><code>deal.stage_changed</code></td><td>تغییر مرحله‌ی یک سرنخ متعلق به همین اکانت</td></tr>
        </tbody>
      </table></div>

      <div class="code-cols" style="margin-top:.5rem">
        <div class="code-block">
          <div class="code-label">بدنه‌ی هر رویداد ارسالی</div>
          <pre><code>{ "event": "ticket.scanned",
  "data": {"ticket_number": "...", "result": "success"},
  "sent_at": "2026-09-27T10:00:00+00:00" }</code></pre>
        </div>
        <div class="code-block">
          <div class="code-label">هدرهای این درخواست</div>
          <pre><code>X-Webhook-Event: ticket.scanned
X-Webhook-Signature: hmac_sha256(body, signing_secret)</code></pre>
        </div>
      </div>
      <div class="callout">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l8 4v6c0 5-3.4 8.4-8 10-4.6-1.6-8-5-8-10V6l8-4Z"/></svg>
        <div>
          <span class="label">اعتبارسنجی امضا</span>
          <p>امضا روی <b>بدنه‌ی خام</b> درخواست محاسبه شده - آن را روی همان بایت‌های خام دریافتی بازمحاسبه کنید (PHP: <span class="mono">hash_hmac('sha256', $rawBody, $secret)</span>)، نه روی نسخه‌ای که دوباره JSON.stringify کرده‌اید.</p>
        </div>
      </div>

      <h3 id="ch-13-errors">مرجع کدهای خطا</h3>
      <div class="tbl"><table class="ref">
        <thead><tr><th>HTTP</th><th>code</th><th>معنی</th></tr></thead>
        <tbody>
          <tr><td>400</td><td class="mono"><code>INVALID_REQUEST</code></td><td>هدر <span class="mono">Idempotency-Key</span> فرستاده نشده.</td></tr>
          <tr><td>401</td><td class="mono"><code>UNAUTHORIZED</code></td><td>کلید/رمز API نامعتبر یا اکانت غیرفعال است.</td></tr>
          <tr><td>403</td><td class="mono"><code>FORBIDDEN</code></td><td>اکانت مجوز این عملیات را ندارد.</td></tr>
          <tr><td>404</td><td class="mono"><code>NOT_FOUND</code></td><td>رکورد یافت نشد، یا متعلق به اکانت دیگری است.</td></tr>
          <tr><td>409</td><td class="mono"><code>CONFLICT</code></td><td>همان Idempotency-Key با بدنه‌ی متفاوت، یا تداخل منبع.</td></tr>
          <tr><td>422</td><td class="mono"><code>VALIDATION_ERROR</code></td><td>ورودی نامعتبر یا ناقص است.</td></tr>
          <tr><td>422</td><td class="mono"><code>CONFIGURATION_ERROR</code></td><td>پیش‌نیازی روی خود اکانت تنظیم نشده.</td></tr>
          <tr><td>429</td><td class="mono"><code>RATE_LIMITED</code></td><td>سقف درخواست در دقیقه‌ی این اکانت رد شده.</td></tr>
        </tbody>
      </table></div>

      <h3 id="ch-13-permissions">مرجع کامل مجوزها (Operations)</h3>
      <p>هر اکانت API یک فهرست «عملیات‌های مجاز» دارد که از پنل «اکانت‌های API خارجی» تنظیم می‌شود.</p>
      <div class="tbl"><table class="ref">
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
      </table></div>
      <div class="callout">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/></svg>
        <div>
          <span class="label">اصل کمترین دسترسی</span>
          <p>فقط عملیات‌هایی که واقعاً استفاده می‌کنید را برای هر اکانت فعال کنید. یک اکانت می‌تواند به فهرست مشخصی از محصولات هم محدود شود (خالی = دسترسی به همه‌ی محصولات).</p>
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
      <div>Laravel 13 · Filament v5.8 · PHP 8.4</div>
      <div>Persian-first · RTL · Jalali calendar · Installable PWA</div>
    </div>
  </div>

</div>
</body>
</html>
