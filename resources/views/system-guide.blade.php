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
  .chapter-body ul:not(.subtoc) { margin: .4rem 0 1rem; padding-inline-start: 1.4rem; display: flex; flex-direction: column; gap: .45rem; }
  .chapter-body ul:not(.subtoc) li { color: var(--ink-soft); font-size: .94rem; max-width: 72ch; }
  .chapter-body ul:not(.subtoc) li b { color: var(--ink); }
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
      <li><a href="#ch-14">مرکز سئو (جزوه‌ی کامل)</a></li>
      <li><a href="#ch-15">وب‌اپ مشتریان (گنجنامه)</a></li>
      <li><a href="#ch-16">تریپ‌پلنر</a></li>
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
      <div class="callout">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
        <div>
          <span class="label">فقط پذیرش هستید؟</span>
          <p>مسیر یک روز کاری را در <a href="#ch-02-simple-day">«سناریوی ساده» فصل اقامتی و هتلداری</a> ببینید؛ از صبح تا بستن روز، بدون خواندن بقیه‌ی راهنما. بالای هر صفحه‌ی بخش اقامتی هم کادر جمع‌شده‌ی <b>«راهنمای ساده‌ی این صفحه»</b> هست که همان سناریو را برای همان صفحه در چند قدم می‌گوید (وضعیت باز/بسته‌بودنش را سامانه یادش می‌ماند) و به بخش کامل راهنما لینک می‌دهد.</p>
        </div>
      </div>
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
          <div class="feature-card"><div class="name">مرکز دستیاران هوشمند</div><div class="desc">فهرست همه‌ی دستیارهای گفتگوی سامانه: دستیار مدیریتی، دستیار حقوقی و دستیارهای سفارشی که خودتان ساخته‌اید یا با شما به اشتراک گذاشته شده؛ هرکدام در همان صفحه گفتگو را باز می‌کنند.</div></div>
        <div class="feature-card"><div class="name">گزارش‌های زمان‌بندی‌شده</div><div class="desc">یک سؤال و یک ساعت مشخص کنید؛ دستیار هر روز همان ساعت سؤال را با دسترسی‌های خود شما جواب می‌دهد و پاسخ را در یک گفتگوی تازه برایتان می‌گذارد.</div></div>
        <div class="feature-card"><div class="name">آمار مصرف دستیاران</div><div class="desc">نمایی از میزان استفاده‌ی هر دستیار: تعداد پیام‌ها، مشورت‌ها، بازخورد 👍/👎 و کارهای خودکارش (منابع زیرنظر، پرسش‌های روزانه).</div></div>
        <div class="feature-card"><div class="name">اتصال واتس‌اپ</div><div class="desc">پاسخ‌گویی یک دستیار سفارشی به پیام‌های واقعی واتس‌اپ (WhatsApp Cloud API). تا وقتی حساب Meta و همه‌ی فیلدهای صفحه تنظیم نشده باشد، غیرفعال می‌ماند.</div></div>
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

      <h3>امکانات گفتگو</h3>
      <div class="tbl"><table class="ref">
        <thead><tr><th>امکان</th><th>چطور کار می‌کند</th></tr></thead>
        <tbody>
          <tr><td>پیشنهاد سؤال برای شروع</td><td>در گفتگوی خالی، چند سؤال آماده می‌بینید که با بخشی که تازه در آن کار می‌کردید (رزرو، مشتری، پروژه…) هماهنگ است و فقط شامل بخش‌هایی است که به آن‌ها دسترسی دارید.</td></tr>
          <tr><td>نمایش مراحل کار</td><td>هنگام پاسخ، به‌جای انتظار خالی می‌بینید دستیار در حال گرفتن اطلاعات از کدام بخش است (مثلاً «در حال گرفتن اطلاعات از «حضور و غیاب»…»).</td></tr>
          <tr><td>متوقف کن</td><td>پاسخ در حال ساخت را با دکمه‌ی «متوقف کن» می‌توانید قطع کنید.</td></tr>
          <tr><td>تأیید پیش از اقدام</td><td>هر کاری که چیزی را در سامانه می‌سازد یا تغییر می‌دهد (مثل ساخت تسک) ابتدا به‌صورت کارت «اقدام در انتظار تأیید شما» می‌آید و فقط با «تأیید و اجرا» انجام می‌شود؛ با «رد کردن» هیچ اتفاقی نمی‌افتد.</td></tr>
          <tr><td>جدول و کپی و چاپ</td><td>جدول‌های پاسخ را می‌توانید دانلود کنید، متن پاسخ را کپی کنید، به پاسخ نمره‌ی خوب یا ضعیف بدهید و کل گفتگو را در یک صفحه‌ی چاپی باز کنید.</td></tr>
          <tr><td>نبودن دسترسی</td><td>اگر به بخشی دسترسی نداشته باشید، دستیار به‌جای عدد می‌گوید کدام بخش لازم است و از چه کسی بخواهید مجوز مشاهده‌ی آن را فعال کند.</td></tr>
        </tbody>
      </table></div>

      <h3>گزارش صبحگاهی، هزینه و نگهداری داده</h3>
      <div class="tbl"><table class="ref">
        <thead><tr><th>موضوع</th><th>توضیح</th></tr></thead>
        <tbody>
          <tr><td>هشدارهای بحرانی هر صبح</td><td>هر روز ساعت ۰۸:۳۰ به وقت تهران، کسانی که حق استفاده از دستیار را دارند، هشدارهای بحرانی (مثلاً پروژه‌ی بیش از بودجه یا تیکت از SLA گذشته) را به‌صورت اعلان می‌گیرند. در روزی که چیزی نیست، چیزی فرستاده نمی‌شود.</td></tr>
          <tr><td>گزارش‌های زمان‌بندی‌شده</td><td>از منوی «گزارش‌های زمان‌بندی‌شده» سؤال و ساعت (۲۴ ساعته، به وقت تهران) را بدهید؛ اجرا در بازه‌های ۱۵ دقیقه‌ای انجام می‌شود، فقط ابزارهای گزارش‌گیری استفاده می‌شود و دکمه‌ی «اجرای همین حالا» برای امتحان هست.</td></tr>
          <tr><td>سقف هزینه و مصرف</td><td>مدیر فنی می‌تواند سقف ماهانه‌ی هزینه (تومان) و توکن تعیین کند؛ با رسیدن به سقف، دستیار تا ماه بعد پیام مناسب می‌دهد. قیمت هر میلیون توکن هم از همان تنظیمات می‌آید.</td></tr>
          <tr><td>نگهداری خروجی ابزارها</td><td>خروجی خام ابزارهای دستیار پس از ۹۰ روز (پیش‌فرض) پاک می‌شود؛ متن گفتگو می‌ماند.</td></tr>
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
      <h3 id="ch-01-consult">کار گروهی دستیارها: مشورت خودکار</h3>
      <p>
        <b>چرا:</b> یک دستیار همه‌چیز نمی‌داند. به‌جای اینکه جواب را کپی کنید و پیش دستیار دیگر ببرید، دستیار خودش در همان چت از دستیار متخصص می‌پرسد.
      </p>
      <ol class="steps">
        <li><b>۱.</b> در مرکز دستیاران، دستیاری که باید جواب بدهد را باز کنید، «قابل‌مشورت توسط دستیارهای دیگر» را روشن کنید و تخصص‌هایش را بنویسید (مثلاً «حقوقی، قرارداد»). دستیار باید مال خودتان یا اشتراکی باشد.</li>
        <li><b>۲.</b> در چت هر دستیار دیگر سؤال را بپرسید. اگر سؤال از تخصص خودش بیرون بود، خودش مشورت می‌گیرد؛ هیچ دکمه‌ای لازم نیست.</li>
        <li><b>۳.</b> پاسخ دستیار مشورت‌شونده با نام خودش در چت دیده می‌شود (کادر «پاسخ دستیار …»)، و دستیار اول بعدش نتیجه را در پاسخ خودش می‌آورد.</li>
        <li><b>۴.</b> دستیار مشورت‌شونده با <b>دانش‌نامه‌ی خودش</b> جواب می‌دهد و اگر مالکش ابزار «اتاق خالی»، «جست‌وجوی مشتری» یا «تیکت‌ها» را به آن داده باشد، همان‌ها را هم می‌تواند بخواند؛ با دسترسی‌های خود شما، نه بیشتر.</li>
      </ol>
      <p>
        محدودیت‌ها: در هر پیام حداکثر ۳ مشورت. دستیار مشورت‌شونده می‌تواند خودش از یک دستیار دیگر بپرسد (حداکثر ۲ بار و فقط یک سطح عمیق‌تر: الف ← ب ← ج، و ج از کسی نمی‌پرسد). به کسی که قبلاً در همان زنجیره بوده برنمی‌گردد، پس دور و زنجیرهٔ بی‌پایان ممکن نیست؛ در کارت پاسخ می‌نویسد از چه کسی هم پرسیده. تاریخچه‌ی مشورت‌ها در تنظیمات همان دستیار ثبت می‌شود.
      </p>
      <p><b>تحویل گفتگو (وقتی کل ادامه‌ی کار باید با دستیار دیگری باشد)</b></p>
      <ol class="steps">
        <li><b>۱.</b> فرق با مشورت: در مشورت، دستیار اول سؤال تکی می‌پرسد و خودش جواب را جمع‌بندی می‌کند. در <b>تحویل</b>، دستیار اول گفتگو را با یک خلاصه به دستیار متخصص می‌سپارد و او <b>همان لحظه مستقیم به شما جواب می‌دهد</b>.</li>
        <li><b>۲.</b> لازم نیست دکمه‌ای بزنید یا چیزی کپی کنید؛ در چت یک نوار «گفتگو را به «X» سپرد» می‌آید و پاسخ‌ها با نام دستیار نوشته‌شان نمایش داده می‌شوند.</li>
        <li><b>۳.</b> از آن لحظه گفتگو مال دستیار جدید است و پیام‌های بعدی شما را همان جواب می‌دهد (در فهرست گفتگوهای او دیده می‌شود). او هم می‌تواند دوباره به دستیار دیگری بسپارد، ولی در همان پیام که تازه به او سپرده شده این کار را نمی‌کند (تا گفتگو بین دو دستیار پاس‌کاری نشود).</li>
        <li><b>۴.</b> شرط‌ها مثل مشورت است: دستیار مقصد باید «قابل‌مشورت» باشد و برای شما دیده شود.</li>
      </ol>
      <p><b>دستیار مدیریتی هم از دستیارهای شما می‌پرسد</b></p>
      <p>در «دستیار هوشمند» (دستیار مدیریتی)، اگر دستیار سفارشی «قابل‌مشورت» دارید و مجوز گزارش‌گیری دارید، سؤال تخصصی را از او می‌پرسد و جواب را با نام همان دستیار به شما نشان می‌دهد. این مشورت فقط خواندنی است و هیچ اقدامی انجام نمی‌دهد؛ ثبتش در گزارش فعالیت دستیار هوشمند می‌آید. دستیار مدیریتی چت را به دستیار سفارشی تحویل نمی‌دهد و برعکس هم نیست.</p>
      <p><b>اتاق فرماندهی دستیاران (برای نظارت)</b></p>
      <p>در منوی «دستیار هوشمند مدیریتی» ← «نقشهٔ تیم دستیاران»، دستیارها را مثل یک تیم در یک فضای سه‌بعدی می‌بینید: دستیار مدیریتی در مرکز و بقیه دورش. خط آبی یعنی مشورت و خط نارنجی یعنی تحویل گفتگو؛ ذره‌های نورانی روی خط‌ها یعنی پیام در جریان است و هر چه همکاری بیشتر باشد خط ضخیم‌تر می‌شود. دستیاری که در ۵ دقیقهٔ اخیر کار کرده یک حلقه دورش دارد و چراغش در فهرست «اعضای تیم» سبز است. بکشید تا بچرخد، اسکرول کنید تا زوم شود و روی یک دستیار (در نقشه یا فهرست) بزنید تا دوربین به سمتش برود و آمارش را ببینید. «مخابرات اخیر» آخرین همکاری‌ها را نشان می‌دهد، کنار هر دستیار یک ابر نظر نشان می‌دهد که آخرین پاسخش به <b>خود شما</b> بوده (پاسخ به دیگران دیده نمی‌شود؛ با دکمهٔ «نظرها» خاموش می‌شود و متن کامل در کارت دستیار انتخاب‌شده هست). «ربات / کارتونی» سبک آواتارها را عوض می‌کند (ربات: آنتن، گوش پیچ‌دار و صفحهٔ چهره با چشم‌های LED؛ انتخاب شما در همان مرورگر یادآوری می‌شود)، «تمام‌صفحه» برای نمایش روی صفحهٔ بزرگ است و صفحه هر ۱۰ ثانیه خودش تازه می‌شود. فقط دستیارهای خودتان و اشتراکی‌ها دیده می‌شوند. برای دیدن صفحه، مجوز «دسترسی» آن را به نقش بدهید.</p>

      <p><b>اتاق عملیات (کار تیمی دستیارها روی یک مأموریت)</b></p>
      <ol class="steps">
        <li><b>۱.</b> از منوی «دستیار هوشمند مدیریتی» ← «اتاق عملیات» ← «مأموریت جدید». سه قدم است: (۱) عنوان و هدف را بنویسید، (۲) تیم را بچینید — روی هر جایگاه بزنید و دستیار را از میان کارت‌ها (با آواتار، توضیح و برچسب ابزارهایش) انتخاب کنید: یک یا چند <b>متخصص</b>، یک <b>منتقد</b> (پیشنهاد می‌شود) و یک <b>هماهنگ‌کننده</b>؛ برای هر جایگاه می‌توانید یک دستور کوتاه بنویسید — و (۳) اگر خواستید شرط پایان و سقف دورها را تنظیم کنید. دکمهٔ «شروع سریع» یک مأموریت نمونه («رزرو و پشتیبانی هفتهٔ آینده») را پر می‌کند و فقط دستیارها را شما انتخاب می‌کنید.</li>
        <li><b>۲.</b> «شروع کار تیم» را بزنید (ممکن است چند دقیقه طول بکشد). متخصص‌ها یافته‌هایشان را روی <b>تخته</b> می‌گذارند، هرکدام با منبع و میزان اطمینان؛ یافتهٔ بی‌منبع خودکار «اطمینان پایین» و «بدون منبع» می‌خورد. منتقد ادعاهای ضعیف را زیر همان کارت نقد می‌کند.</li>
        <li><b>۳.</b> هماهنگ‌کننده یک <b>پیش‌نویس تصمیم</b> می‌نویسد: خلاصه، تصمیم‌های پیشنهادی و بخش «چه چیزی مطمئن نیست». این‌ها فقط پیشنهادند و تا تأیید شما هیچ اقدامی انجام نمی‌شود؛ تصمیم و اجرا با خود شماست.</li>
        <li><b>۴.</b> اگر دور اول کافی نبود دوباره اجرا کنید (تا سقف دورهایی که تعیین کرده‌اید)؛ دور بعد یافته‌های قبلی را می‌بیند و فقط چیز تازه می‌گوید. اگر اجرا خطا داد، چیزی نیمه‌کاره ثبت نمی‌شود و همان دور را دوباره اجرا می‌کنید.</li>
      </ol>
      <p><b>نقشه در موبایل و تبلت:</b> در صفحه‌های کوچک نقشه با چیدمان جدا نمایش داده می‌شود: نوار بالا فشرده است (انتخاب تیم در یک ردیف و دکمه‌ها زیرش)، اعضای تیم به‌صورت نوار افقی قابل‌کشیدن زیر آن هستند و با زدن روی هرکدام دوربین به سمتش می‌رود، کارت جزئیات روی پایین نقشه می‌نشیند، شمارنده‌ها در یک ردیف چهارتایی‌اند و «مخابرات اخیر» زیر نقشه آمده است. دکمهٔ تمام‌صفحه فقط وقتی مرورگر پشتیبانی کند دیده می‌شود (روی آیفون معمولاً نیست).</p>
      <p><b>مأموریت روی نقشهٔ تیم:</b> در «اتاق عملیات» دکمهٔ «نمایش روی نقشه» (یا انتخاب مأموریت از بالای «نقشهٔ تیم دستیاران») نقشه را فقط به تیم همان مأموریت تغییر می‌دهد: هماهنگ‌کننده در مرکز، کنار هر عضو یک نشان نقش، خط سبز = گزارش یافته‌ها (متخصص ← منتقد یا هماهنگ‌کننده) و خط قرمز = بازبینی منتقد. ابر نظر هر عضو آخرین کارتی است که روی تخته گذاشته. وقتی مأموریت «در حال اجرا» است، همهٔ اعضا فعال نشان داده می‌شوند.</p>
      <p><b>اجرا در پس‌زمینه:</b> بعد از «شروع کار تیم» لازم نیست صفحه باز بماند. تیم عضو به عضو (هر عضو یک کار کوتاه در صف) کار می‌کند؛ بالای صفحه می‌بینید الان کدام دستیار مشغول است و چند مرحله مانده، و صفحه خودش تازه می‌شود. روی نقشهٔ تیم هم فقط همان عضوِ مشغول می‌درخشد. با «توقف» تیم بعد از پایان مرحلهٔ فعلی می‌ایستد و دورِ نیمه‌کاره کنار گذاشته می‌شود تا دوباره اجرایش کنید. هر مأموریت یک <b>سقف مصرف (توکن)</b> دارد: مصرف تا الان پایین کارت دیده می‌شود، اگر به سقف برسد تیم می‌ایستد و می‌توانید سقف را بالا ببرید. مراحل با کرونِ صف روی هاست انجام می‌شوند، پس شروع هر مرحله تا یک دقیقه تأخیر دارد.</p>
      <p><b>هدایت تیم در حین کار:</b> (۱) زیر هر یافتهٔ روی تخته «✓ تأیید می‌کنم» یا «✕ رد می‌کنم» هست. یافتهٔ ردشده دیگر در چیزی که تیم برای دور بعد می‌خواند، در نقد و در بخش «چه چیزی مطمئن نیست» نمی‌آید و از نقشه هم کنار می‌رود؛ یافتهٔ تأییدشده با برچسب «تأییدشدهٔ مدیر» به تیم می‌رسد و دیگر «بدون منبع» حساب نمی‌شود. «بازگرداندن» هر دو را برمی‌گرداند. (در لحظهٔ اجرای تیم این دکمه‌ها غیرفعال‌اند.) (۲) کادر «پیام به تیم» برای دستور تازه است («روی ویلاهای شمال تمرکز کن»): تا ۱۰ پیام؛ هر مرحله‌ای که هنوز شروع نشده آن را می‌خواند، پس وسط اجرا هم اثر می‌کند.</p>
      <p><b>مأموریت تکرارشونده و اعلان‌ها:</b> در کارت «🔁 تکرار خودکار» هر مأموریت می‌توانید هر روز / هر هفته (روز دلخواه) / هر ماه (روز ۱ تا ۲۸) و ساعت (به وقت تهران) را تعیین کنید. سر وقت، تیم خودش یک دور تازه شروع می‌کند: تخته و شمارندهٔ توکن از نو شروع می‌شود و نتیجهٔ دورِ قبل در «🕘 اجراهای قبلی» (با تاریخ، دستی یا زمان‌بندی‌شده، پیشنهادها و «مطمئن نیست») می‌ماند. هر اجرای زمان‌بندی‌شده یک دور می‌دهد و اگر شروع نشود (مثلاً دستیاری حذف یا سقف پر شده) خبرتان می‌کنند. اعلان‌ها (در زنگ پنل و پوش) برای: آماده‌شدن پیش‌نویس تصمیم، پایان اجرا بدون پیشنهاد، شکست اجرا، و رسیدن مصرف به ۸۰٪ سقف توکن (هر اجرا یک بار). مراحل با کرونِ هاست اجرا می‌شوند، پس زمان‌بندی تا چند دقیقه تأخیر دارد.</p>
      <p><b>نمونه‌های آماده و ابزارهای تازه:</b> بالای فرم «مأموریت جدید» چهار نمونه هست: «وضعیت رزرو و پشتیبانی هفتهٔ آینده»، «گزارش صبحگاهی مدیریت»، «عملکرد هفتهٔ اقامتگاه» و «جمع‌بندی ماه». هر نمونه هدف، دستورها و ابزارهای لازم هر متخصص را پر می‌کند و یک تکرار پیشنهادی دارد (تیک «با تکرار پیشنهادی ساخته شود» را بزنید تا از همان لحظه تکرار شود). دستیارها را خودتان انتخاب می‌کنید؛ در پنجرهٔ انتخاب، دستیارهایی که ابزارهای لازم را دارند اول می‌آیند و «مناسب این جایگاه» می‌گیرند. برای همین سه ابزار خواندنیِ تازه به فهرست ابزارهای دستیارهای سفارشی اضافه شد (در «مرکز دستیاران» برای هر دستیار فعال کنید): «گزارش عملکرد (اشغال، ADR، RevPAR)»، «پیش‌بینی اشغال» و «وضعیت عملیاتی امروز». این ابزارها هم مثل بقیه فقط با مجوز «رزروها» برای کاربر کار می‌کنند.</p>
      <p><b>داشبورد اتاق عملیات:</b> وقتی در «اتاق عملیات» هیچ مأموریتی را باز نکرده‌اید، یک نمای کلی می‌بینید: پنج شمارنده (منتظر نظر شما، ناموفق یا متوقف، در حال اجرا، تکرارشونده، توکن مصرف‌شده در این ماه)، فهرست «🔔 نیاز به شما» (اول مأموریت‌هایی که پیشنهادشان منتظر تصمیم شماست، بعد ناموفق‌ها با دلیلشان)، «⏳ در حال اجرا» با نوار پیشرفت، «🔁 تکرارشونده‌ها» با اجرای بعدی و «🕘 آخرین نتیجه‌ها». با «باز کردن» مستقیم وارد همان مأموریت می‌شوید. فقط مأموریت‌های خودتان شمرده می‌شود و وقتی چیزی در حال اجراست صفحه هر ۵ ثانیه تازه می‌شود.</p>
      <p><b>اقدام‌های پیشنهادی و صف تأیید:</b> هماهنگ‌کننده علاوه بر «تصمیم‌های پیشنهادی» می‌تواند چند <b>اقدام مشخص</b> هم پیشنهاد بدهد (فعلاً «ایجاد وظیفه» با دپارتمان، مهلت و اولویت؛ حداکثر ۵ مورد در هر دور). این اقدام‌ها <b>هیچ‌وقت خودکار انجام نمی‌شوند</b>: در کارت «✅ اقدام‌های پیشنهادی تیم» داخل مأموریت و در «✅ صف تأیید اقدام‌ها» بالای داشبورد اتاق عملیات (برای همهٔ مأموریت‌های شما یک‌جا) می‌آیند و هر کدام جدا «تأیید و انجام» یا «رد» می‌شود. بعد از تأیید، اقدام فقط یک بار و با مجوزهای خودِ شما اجرا می‌شود (کسی که «اقدام» را در دستیار هوشمند ندارد، اصلاً پیشنهادی برایش ثبت نمی‌شود و تأیید هم نمی‌کند)؛ اگر اجرا نشود (مثلاً دپارتمان پیدا نشود) با دلیلش «انجام نشد» می‌خورد. اقدامی که از قبل منتظر یا انجام‌شده باشد در دورهای بعد دوباره پیشنهاد نمی‌شود. فقط سازندهٔ مأموریت می‌تواند تأیید یا رد کند و شمارندهٔ «اقدام منتظر تأیید» روی داشبورد هست.</p>
      <p>محدودیت‌ها: اعضای مأموریت فقط ابزارهای خواندنیِ تنظیم‌شدهٔ همان دستیار را دارند (تغییر داده فقط از راه صف تأیید و با تأیید شما) (به اندازهٔ مجوزهای خودِ شما) و از دستیار دیگری نمی‌پرسند. مأموریت‌ها فقط برای سازنده‌شان دیده می‌شود. هر دور دستی شروع می‌شود (مأموریت تکرارشونده در مرحلهٔ بعد می‌آید).</p>
      <p>دکمه‌ی «فوروارد گفتگو» همچنان هست و کل گفتگو را دستی (بدون پاسخ خودکار) به دستیار دیگر کپی می‌کند.</p>
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
      <h3 id="ch-02-simple-day">سناریوی ساده: یک روز کاری پذیرش</h3>
      <p>
        اگر فقط می‌خواهید کار را راه بیندازید، همین مسیر را بروید و بقیه‌ی فصل را فقط وقتی لازم شد باز کنید.
        همه‌ی منوها در گروه <b>«اقامتی و هتلداری»</b> هستند.
      </p>

      <p><b>یک بار، پیش از شروع</b></p>
      <ol class="steps">
        <li><b>۱.</b> <b>انواع اتاق/ویلا:</b> برای هر نوع، قیمت پایه، ساعت ورود و ساعت خروج را بگذارید.</li>
        <li><b>۲.</b> <b>اتاق‌ها و ویلاها:</b> هر واحد را بسازید و نوعش را انتخاب کنید.</li>
        <li><b>۳.</b> <b>خدمات و هزینه‌های جانبی:</b> چند مورد رایج (صبحانه، لباسشویی، مینی‌بار) را تعریف کنید.</li>
        <li><b>۴.</b> <b>پیام‌های رزرو</b> (اختیاری): «پیامک تأیید» را روشن کنید. تا روشن نکنید هیچ پیامکی نمی‌رود.</li>
      </ol>

      <p><b>هر صبح (۲ دقیقه)</b></p>
      <ol class="steps">
        <li><b>۱.</b> <b>ورود و خروج امروز</b> را باز کنید و باز نگه دارید. بالای صفحه نوارهای نارنجی، یادداشت‌های شیفت قبل، اتاق‌های خارج از سرویس و اشیای جامانده را نشان می‌دهد.</li>
        <li><b>۲.</b> اگر تیم نظافت کاغذ می‌خواهد، دکمه‌ی <b>«برگه‌ی نظافت امروز»</b> را بزنید و چاپ کنید.</li>
      </ol>

      <p><b>وقتی مهمان تلفن می‌زند یا می‌آید</b></p>
      <ol class="steps">
        <li><b>۱.</b> <b>رزروها ← ایجاد:</b> مهمان، اتاق و تاریخ را بدهید؛ قیمت خودکار می‌آید.</li>
        <li><b>۲.</b> پیش‌پرداخت گرفتید؟ روی همان ردیف <b>«ثبت پرداخت»</b> را بزنید.</li>
        <li><b>۳.</b> رزرو قبلی را می‌خواهید؟ در فهرست رزروها نام، موبایل (به هر شکل)، کد ملی یا نام اتاق را جست‌وجو کنید. تب‌های بالای فهرست (ورود امروز، مقیم، آینده، لغو شده) هم کمک می‌کنند.</li>
        <li><b>۴.</b> اتاق خالی نبود؟ مهمان را در <b>لیست انتظار</b> بنویسید؛ با خالی‌شدن اتاق خودکار پیامک می‌گیرد.</li>
      </ol>

      <p><b>روز ورود</b></p>
      <ol class="steps">
        <li><b>۱.</b> در <b>ورود و خروج امروز</b> دکمه‌ی <b>«چک‌این»</b> را بزنید. اگر زیر نام مهمان هشدار پذیرش یا «مهمان مسدود است» دیدید، اول آن را بخوانید.</li>
        <li><b>۲.</b> اتاق خودکار «اشغال» می‌شود.</li>
      </ol>

      <p><b>در طول اقامت</b></p>
      <ol class="steps">
        <li><b>۱.</b> <b>«افزودن هزینه»</b> روی رزرو: صبحانه، لباسشویی و …</li>
        <li><b>۲.</b> مهمان اتاق یا مدت را عوض کرد؟ <b>«تغییر اتاق»</b> یا <b>«تمدید اقامت»</b> روی همان رزرو.</li>
        <li><b>۳.</b> اتاقی خراب شد؟ <b>اتاق‌های خارج از سرویس ← ایجاد</b> (یا وضعیت اتاق را «خارج از سرویس» بزنید). تا برنگردانید فروخته نمی‌شود.</li>
        <li><b>۴.</b> چیزی هست که شیفت بعد باید بداند؟ در <b>دفتر تحویل شیفت</b> بنویسید.</li>
      </ol>

      <p><b>روز خروج</b></p>
      <ol class="steps">
        <li><b>۱.</b> <b>چاپ صورت‌حساب</b>، تسویه‌ی مانده با <b>«ثبت پرداخت»</b>، سپس <b>«چک‌اوت»</b>.</li>
        <li><b>۲.</b> تسک نظافت خودکار ساخته می‌شود و اتاق «نیازمند نظافت» است.</li>
        <li><b>۳.</b> نظافت که تمام شد، در <b>رک اتاق</b> دکمه‌ی <b>«نظافت انجام شد»</b> را بزنید (یا تسک را ببندید)؛ اتاق دوباره آماده‌ی فروش است.</li>
        <li><b>۴.</b> مهمان چیزی جا گذاشت؟ روی رزرو <b>«ثبت شیء جامانده»</b> را بزنید و از فهرست <b>اشیای جامانده</b> به او خبر بدهید.</li>
      </ol>

      <p><b>آخر شب (۱ دقیقه)</b></p>
      <ol class="steps">
        <li><b>۱.</b> <b>بستن روز:</b> موارد ناتمام (مهمانی که نیامد، مانده‌ی پرداخت) را ببینید و روز را ببندید.</li>
        <li><b>۲.</b> هفته‌ای یک بار <b>گزارش‌های هتلداری</b> را نگاه کنید.</li>
      </ol>

      <div class="callout">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
        <div>
          <span class="label">ساده‌ترین قاعده</span>
          <p>صبح «ورود و خروج امروز»؛ در طول روز «رزروها» و «رک اتاق»؛ آخر شب «بستن روز». بقیه‌ی امکانات را فقط وقتی لازم شدند باز کنید.</p>
        </div>
      </div>

      <h3 id="ch-02-map">نقشه‌ی فصل: هر کار را کجا پیدا کنم؟</h3>
      <div class="tbl"><table class="ref">
        <thead><tr><th>می‌خواهم …</th><th>بروم به</th></tr></thead>
        <tbody>
          <tr><td>ببینم امروز چه خبر است</td><td><a href="#ch-02-simple-day">سناریوی ساده</a> · <a href="#ch-02-morning-digest">خلاصه‌ی صبحگاهی</a> · <a href="#ch-02-desk-reminders">یادآوری‌های پذیرش</a> · <a href="#ch-02-room-rack">رک اتاق</a></td></tr>
          <tr><td>رزرو بگیرم و پول دریافت کنم</td><td><a href="#ch-02-payments">صورت‌حساب و پرداخت</a> · <a href="#ch-02-online-payment">پرداخت آنلاین و چاپ صورت‌حساب</a> · <a href="#ch-02-booking-search">جست‌وجوی رزرو</a> · <a href="#ch-02-booking-tabs">تب‌های رزرو</a> · <a href="#ch-02-groups">رزرو گروهی</a></td></tr>
          <tr><td>قیمت و فروش را تنظیم کنم</td><td><a href="#ch-02-pricing">قیمت‌گذاری پیشرفته</a> · <a href="#ch-02-rate-plans">نرخ‌های فروش</a> · <a href="#ch-02-allotment">سهمیه‌ی کانال‌ها</a> · <a href="#ch-02-services">خدمات جانبی</a></td></tr>
          <tr><td>لغو، عدم حضور یا تغییر را مدیریت کنم</td><td><a href="#ch-02-cancellation">سیاست لغو</a> · <a href="#ch-02-change">تمدید و تغییر اتاق</a> · <a href="#ch-02-deposit">ودیعه‌ی خسارت</a> · <a href="#ch-02-waitlist">لیست انتظار</a></td></tr>
          <tr><td>به مهمان پیام بدهم یا اطلاعات بگیرم</td><td><a href="#ch-02-messages">پیامک‌های خودکار</a> · <a href="#ch-02-balance-reminder">یادآوری مانده‌ی حساب</a> · <a href="#ch-02-precheckin">پیش‌ثبت‌نام آنلاین</a> · <a href="#ch-02-stay-times">ساعت ورود و خروج</a> · <a href="#ch-02-guest-flags">هشدار پذیرش و مسدودکردن</a></td></tr>
          <tr><td>اتاق‌ها، نظافت و موجودی را کنترل کنم</td><td><a href="#ch-02-inventory">موجودی و اشغال</a> · <a href="#ch-02-room-blocks">اتاق‌های خارج از سرویس</a> · <a href="#ch-02-cleaning-link">نظافت و وضعیت اتاق</a> · <a href="#ch-02-housekeeping-sheet">برگه‌ی نظافت</a> · <a href="#ch-02-calendar">تقویم اشغال</a> · <a href="#ch-02-late-checkout">دیرکرد در خروج</a></td></tr>
          <tr><td>کار شیفت‌ها را هماهنگ کنم</td><td><a href="#ch-02-handover">دفتر تحویل شیفت</a> · <a href="#ch-02-lost-found">اشیای جامانده</a></td></tr>
          <tr><td>پایان روز و گزارش‌ها</td><td><a href="#ch-02-night-audit">بستن روز</a> · <a href="#ch-02-reports">گزارش‌های هتلداری</a></td></tr>
        </tbody>
      </table></div>

      <div class="feature-grid">
        <div class="feature-card"><div class="name">اتاق‌ها و انواع اتاق/ویلا</div><div class="desc">تعریف کامل موجودی اقامتگاه و دسته‌بندی هر واحد بر اساس نوع.</div></div>
        <div class="feature-card"><div class="name">رک اتاق — وضعیت زنده</div><div class="desc">نمای کارتی از همه‌ی واحدها: خالی، اشغال، نیازمند نظافت یا خارج از سرویس، به‌همراه مهمان مقیم و رزرو بعدی.</div></div>
        <div class="feature-card"><div class="name">رزروها</div><div class="desc">ثبت رزرو با بررسی خودکار تداخل تاریخ/اتاق، و دکمه‌های اختصاصی چک‌این و چک‌اوت سریع روی جدول.</div></div>
        <div class="feature-card"><div class="name">کانال‌های فروش و سهمیه‌بندی</div><div class="desc">اتصال به سایت‌های واسط، مدیریت سهمیه‌ی هر کانال و داشبورد یکپارچه‌ی مدیریت کانال.</div></div>
        <div class="feature-card"><div class="name">قیمت‌گذاری پویا</div><div class="desc">تعریف قواعد قیمتی فصلی/شرطی برای نرخ اتاق‌ها.</div></div>
        <div class="feature-card"><div class="name">سیاست لغو و عدم حضور</div><div class="desc">قاعده‌ی پله‌ای جریمه‌ی لغو (چند روز مانده، چند درصد)، ذخیره‌ی شرایط روی هر رزرو، لغو با محاسبه‌ی خودکار جریمه، بازگشت پول کیف پول و ثبت عدم حضور.</div></div>
        <div class="feature-card"><div class="name">ورود و خروج امروز</div><div class="desc">صفحه‌ی صبحگاهی پذیرش: ورودی‌ها، خروجی‌ها، دیرکرد در خروج، مانده‌ی حساب، هشدار مهمان و نوارهای یادآوری؛ با چک‌این و چک‌اوت روی خود ردیف.</div></div>
        <div class="feature-card"><div class="name">اتاق‌های خارج از سرویس</div><div class="desc">یک فهرست واحد برای تعمیر و بسته‌بودن واحد (با تاریخ یا تا اطلاع بعدی)؛ وضعیت اتاق، رک، رزرو و کانال‌ها همه از همین می‌خوانند.</div></div>
        <div class="feature-card"><div class="name">نظافت و برگه‌ی چاپی</div><div class="desc">تسک نظافت بعد از چک‌اوت و تغییر اتاق، دکمه‌ی «نظافت انجام شد» روی رک و برگه‌ی A4 روزانه برای تیم نظافت.</div></div>
        <div class="feature-card"><div class="name">دفتر تحویل شیفت</div><div class="desc">هر چیزی که شیفت بعد باید بداند، با ثبت‌کننده و زمان؛ تا «انجام شد» روی داشبورد صبحگاهی می‌ماند.</div></div>
        <div class="feature-card"><div class="name">اشیای جامانده</div><div class="desc">ثبت از روی رزرو، پیامک به مهمان، و تحویل یا بایگانی.</div></div>
        <div class="feature-card"><div class="name">پیام‌ها و یادآوری‌های خودکار</div><div class="desc">تأیید رزرو، یادآوری ورود، مانده‌ی حساب و نظرسنجی؛ همه خاموش تا مدیر روشن کند. خلاصه‌ی صبحگاهی برای مدیر.</div></div>
        <div class="feature-card"><div class="name">بستن روز و گزارش‌ها</div><div class="desc">ممیزی شبانه، اشغال، ADR، RevPAR، کانال‌ها و پیش‌بینی ۳۰ روزه.</div></div>
        <div class="feature-card"><div class="name">صورت‌حساب و پرداخت‌ها</div><div class="desc">هر رزرو یک دفتر پول دارد: پرداخت نقدی، کارت‌خوان، کارت‌به‌کارت، آنلاین، پیش‌پرداخت و استرداد، همراه با مانده‌ی لحظه‌ای و وضعیت پرداخت. پرداخت از کیف پول هم در همان حساب جمع می‌شود.</div></div>
      </div>
      <div class="callout">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round"><path d="M2 8.5a15 15 0 0 1 20 0M5.5 12a10 10 0 0 1 13 0M9 15.5a5 5 0 0 1 6 0"/><circle cx="12" cy="19" r="1" fill="currentColor" stroke="none"/></svg>
        <div>
          <span class="label">اولین قدم آفلاین‌سازی</span>
          <p>صفحه‌ی «رک اتاق» به‌عنوان اولین بخش سامانه، حتی بدون هیچ اتصال اینترنتی هم باز می‌شود و آخرین وضعیت
          ذخیره‌شده‌ی اتاق‌ها را نشان می‌دهد — درست مثل یک اپلیکیشن نصب‌شده. جزئیات کامل در فصل «کار بدون اینترنت».</p>
        </div>
      </div>
      <h3 id="ch-02-payments">صورت‌حساب و پرداخت یک رزرو</h3>
      <p>هر رزرو با ساخته‌شدن یک قلم «اقامت N شب» می‌گیرد؛ اقلام دیگر (مینی‌بار، رستوران و …) از پنل «اقلام صورتحساب» اضافه می‌شود. «مانده» = کل صورت‌حساب − پرداخت از کیف پول − پولِ دریافتی.</p>
      <ol class="steps">
        <li><b>۱.</b> در فهرست رزروها روی ردیف، دکمه‌ی <b>«ثبت پرداخت»</b> را بزنید (یا در صفحه‌ی خود رزرو، پنل «پرداخت‌ها»).</li>
        <li><b>۲.</b> «نوع» را انتخاب کنید: <b>پرداخت</b> (دریافت پول)، <b>پیش‌پرداخت</b> (برای نگه‌داشتن رزرو؛ مثل پرداخت از صورت‌حساب کم می‌شود) یا <b>استرداد</b> (برگرداندن پول به مهمان).</li>
        <li><b>۳.</b> «روش» (نقدی، کارت‌خوان، کارت‌به‌کارت/حواله، آنلاین، سایر)، «مبلغ» و در صورت لزوم «شماره پیگیری / رسید» را بنویسید. مبلغ پیش‌فرض همان مانده است.</li>
        <li><b>۴.</b> ستون‌های «صورت‌حساب» و «مانده» در فهرست رزروها، و فیلتر «فقط رزروهای دارای مانده»، نشان می‌دهند چه کسی هنوز بدهکار است. هنگام «چک‌اوت»، اگر مانده‌ای باشد، پیام هشدار می‌بینید (چک‌اوت مسدود نمی‌شود).</li>
      </ol>
      <div class="tbl"><table class="ref">
        <thead><tr><th>قاعده</th><th>توضیح</th></tr></thead>
        <tbody>
          <tr><td>سقف پرداخت</td><td>پرداخت یا پیش‌پرداخت از مانده‌ی صورت‌حساب بیشتر نمی‌شود، و برای رزرو لغوشده ثبت نمی‌شود.</td></tr>
          <tr><td>سقف استرداد</td><td>استرداد از مجموع پولِ دریافت‌شده (نقد، کارت، حواله، آنلاین) بیشتر نمی‌شود. پرداخت از کیف پول از این راه برگشت داده نمی‌شود.</td></tr>
          <tr><td>ویرایش‌ناپذیری</td><td>ردیف‌های پرداخت ویرایش یا حذف نمی‌شوند؛ اشتباه را با یک «استرداد» اصلاح کنید تا دفتر مالی قابل اعتماد بماند. هر ردیف زمان و ثبت‌کننده را دارد.</td></tr>
          <tr><td>API بیرونی</td><td>پاسخ رزرو در API علاوه بر <span class="mono">total_amount</span> این‌ها را هم دارد: <span class="mono">paid_amount</span>، <span class="mono">balance_due</span> و <span class="mono">payment_status</span> (unpaid، partial، paid، overpaid).</td></tr>
        </tbody>
      </table></div>
      <h3 id="ch-02-cancellation">سیاست لغو، لغو رزرو و عدم حضور</h3>
      <p>
        <b>چرا:</b> بدون قاعده‌ی روشن، هر لغو یک بحث است. «سیاست لغو» می‌گوید هر لغو چقدر جریمه دارد و سامانه جریمه و
        بازگشت پول را خودش حساب می‌کند.
      </p>
      <ol class="steps">
        <li><b>۱.</b> <b>اقامتی و هتلداری ← سیاست‌های لغو ← ایجاد.</b> نام بدهید و «پله‌ها» را بنویسید: «دست‌کم N روز مانده به ورود ← X٪ جریمه». مثال: ۷ روز ← ۰٪، ۳ روز ← ۳۰٪، ۱ روز ← ۷۰٪.</li>
        <li><b>۲.</b> «جریمه‌ی لغو دیرتر از همه‌ی پله‌ها» و «جریمه‌ی عدم حضور» را به درصد بدهید (پیش‌فرض ۱۰۰٪).</li>
        <li><b>۳.</b> یکی را «پیش‌فرض» کنید. هر رزرو تازه شرایط همان سیاست را <b>همان لحظه روی خودش ذخیره می‌کند</b>؛ تغییر بعدی سیاست روی رزروهای قبلی اثر ندارد (چیزی که به مهمان گفته‌اید عوض نمی‌شود). در فرم رزرو می‌توان سیاست دیگری هم انتخاب کرد.</li>
        <li><b>۴.</b> برای لغو: در فهرست رزروها روی ردیف <b>«لغو رزرو»</b> بزنید. پنجره شرایط آن رزرو و جریمه‌ی همین امروز را نشان می‌دهد. «دلیل» اختیاری است و «جریمه‌ی لغو بخشیده شود» جریمه را صفر می‌کند.</li>
        <li><b>۵.</b> اگر مهمان نیامد: <b>«عدم حضور»</b> (از روز ورود به بعد دیده می‌شود) با درصد جریمه‌ی مخصوص عدم حضور.</li>
      </ol>
      <div class="tbl"><table class="ref">
        <thead><tr><th>بعد از لغو</th><th>چه می‌شود</th></tr></thead>
        <tbody>
          <tr><td>صورت‌حساب</td><td>فقط «جریمه‌ی لغو» می‌ماند؛ «مانده» همان جریمه منهای پرداخت‌هاست و با «ثبت پرداخت» می‌توان آن را گرفت.</td></tr>
          <tr><td>پول کیف پول</td><td>اول جریمه از پول دریافتی (نقد، کارت، حواله، آنلاین) کم می‌شود؛ هر مبلغ کیف پولی که برای جریمه لازم نباشد، خودکار به کیف پول مهمان برمی‌گردد.</td></tr>
          <tr><td>پول نقد</td><td>مازاد پول دریافتی به‌صورت «قابل استرداد نقدی» نشان داده می‌شود و شما با «ثبت پرداخت ← استرداد» آن را ثبت می‌کنید (پول نقد خودکار پس داده نمی‌شود).</td></tr>
          <tr><td>اتاق</td><td>برای همان تاریخ‌ها دوباره آزاد می‌شود.</td></tr>
          <tr><td>تغییر دستی وضعیت</td><td>اگر در ویرایش رزرو وضعیت را «لغو شده» کنید، همان سیاست و همان حساب‌وکتاب اعمال می‌شود. برگرداندن رزرو به وضعیت فعال، اطلاعات لغو را پاک می‌کند.</td></tr>
          <tr><td>API بیرونی</td><td>پاسخ رزرو شامل <span class="mono">cancellation_terms</span>، <span class="mono">cancelled_at</span> و <span class="mono">cancellation_fee</span> است.</td></tr>
        </tbody>
      </table></div>
      <p>رزرو چک‌این‌شده با این دکمه‌ها لغو نمی‌شود؛ برای آن، خروج زودهنگام را با اصلاح اقلام صورت‌حساب و چک‌اوت انجام دهید.</p>
      <h3 id="ch-02-stay-times">ساعت ورود و خروج</h3>
      <p>
        <b>چرا:</b> «ساعت تحویل اتاق چند است؟» پرتکرارترین سؤال مهمان است. حالا برای هر نوع اتاق ساعت ورود و خروج ثبت می‌شود و در پیامک رزرو می‌آید.
      </p>
      <ol class="steps">
        <li><b>۱.</b> در ویرایش «نوع اتاق»، «ساعت ورود» (پیش‌فرض ۱۴:۰۰) و «ساعت خروج» (پیش‌فرض ۱۲:۰۰) را به شکل ۲۴ ساعته بنویسید.</li>
        <li><b>۲.</b> در متن پیامک‌ها (<b>تنظیمات پیامک رزرو</b>) از <span class="mono">{check_in_time}</span> و <span class="mono">{check_out_time}</span> استفاده کنید، مثلاً «ورود از ساعت {check_in_time}، خروج تا {check_out_time}». متن پیش‌فرض پیامک‌ها تغییری نکرده است.</li>
      </ol>
      <h3 id="ch-02-messages">پیامک‌های خودکار رزرو و نظرسنجی</h3>
      <p>
        <b>چرا:</b> مهمانی که تأیید و یادآوری می‌گیرد کمتر نمی‌آید و مهمانی که از او نظر خواسته شود، نظرش را می‌دهد.
        سامانه این سه پیامک را خودش می‌فرستد و هر کدام را در «مکالمات» پرونده‌ی مهمان ثبت می‌کند.
      </p>
      <ol class="steps">
        <li><b>۱.</b> ابتدا «تنظیمات پیامک» را فعال و کامل کنید (پیامک بدون آن ارسال نمی‌شود و در مکالمات «ناموفق» ثبت می‌شود).</li>
        <li><b>۲.</b> <b>اقامتی و هتلداری ← پیام‌های رزرو.</b> هر کدام از سه پیامک را جداگانه روشن کنید؛ <b>همه‌چیز در ابتدا خاموش است</b> تا بی‌خبر به مهمان پیامک نرود.</li>
        <li><b>۳.</b> متن هر پیامک را عوض کنید. متغیرها: <span class="mono">{guest}</span> نام مهمان، <span class="mono">{code}</span> کد رزرو، <span class="mono">{room}</span> اتاق، <span class="mono">{check_in}</span> و <span class="mono">{check_out}</span> تاریخ شمسی ورود و خروج، <span class="mono">{nights}</span> تعداد شب، <span class="mono">{link}</span> لینک نظرسنجی.</li>
        <li><b>۴.</b> برای ارسال دستی (مثلاً مهمان پیامک را نگرفته): در فهرست رزروها روی ردیف <b>«پیامک رزرو»</b> بزنید.</li>
      </ol>
      <div class="tbl"><table class="ref">
        <thead><tr><th>پیامک</th><th>چه زمانی</th></tr></thead>
        <tbody>
          <tr><td>تأیید رزرو</td><td>لحظه‌ای که رزرو «قطعی» می‌شود (چه ثبت مستقیم قطعی، چه تغییر وضعیت از «در انتظار»). برای هر رزرو فقط یک بار.</td></tr>
          <tr><td>یادآوری ورود</td><td>هر صبح ساعت ۹:۳۰ برای رزروهای قطعی که تا «N روز دیگر» می‌رسند. رزروی که همان امروز ثبت شده یادآوری نمی‌گیرد (تأیید را همین حالا گرفته). برای هر رزرو فقط یک بار.</td></tr>
          <tr><td>نظرسنجی</td><td>بعد از چک‌اوت. مهمان لینک امضاشده‌ای می‌گیرد (۶۰ روز اعتبار) و با عدد ۰ تا ۱۰ و یک نظر کوتاه جواب می‌دهد؛ فقط یک بار می‌تواند جواب دهد. پاسخ در «بازخورد مهمانان» و محاسبه‌ی NPS می‌آید.</td></tr>
        </tbody>
      </table></div>
      <p>اگر پیامکی ارسال نشود (سرویس پیامک خاموش یا بدون شماره)، زمان «ارسال‌شده» روی رزرو ثبت نمی‌شود و ردیف ناموفق با دلیلش در مکالمات مهمان می‌ماند.</p>
      <h3 id="ch-02-allotment">سهمیه‌ی کانال‌های فروش و فهرست ورود/خروج امروز</h3>
      <p>
        <b>چرا:</b> اگر یک سایت واسط بتواند همه‌ی واحدهای یک نوع اتاق را بفروشد، برای فروش مستقیم و کانال‌های دیگر چیزی نمی‌ماند.
        «سهمیه» سقف هر کانال را مشخص می‌کند و سامانه آن را <b>هنگام ثبت رزرو واقعاً اجرا می‌کند</b>
        (پیش‌تر فقط نمایش داده می‌شد).
      </p>
      <ol class="steps">
        <li><b>۱.</b> <b>اقامتی و هتلداری ← سهمیه‌بندی کانال‌ها ← ایجاد.</b> نوع اتاق، کانال و «تعداد سهمیه» را بدهید.</li>
        <li><b>۲.</b> از این به بعد رزروی که با آن کانال و آن نوع اتاق ثبت شود، اگر در <b>همان شب‌ها</b> از سقف بگذرد رد می‌شود و پیام روشن می‌گیرید؛ چه در پنل، چه از API و چه از اپ مشتری.</li>
        <li><b>۳.</b> برای فهرست ورود و خروج هر روز: <b>ورود و خروج امروز.</b> هر ردیف مانده‌ی حساب را نشان می‌دهد و دکمه‌ی چک‌این یا چک‌اوت همان‌جاست.</li>
      </ol>
      <div class="tbl"><table class="ref">
        <thead><tr><th>حالت</th><th>نتیجه</th></tr></thead>
        <tbody>
          <tr><td>ردیف سهمیه نساخته‌اید</td><td>آن کانال برای آن نوع اتاق محدودیتی ندارد (فقط هم‌پوشانی خود اتاق جلوی دو رزرو را می‌گیرد).</td></tr>
          <tr><td>سهمیه صفر</td><td>آن کانال آن نوع اتاق را نمی‌فروشد.</td></tr>
          <tr><td>شمارش</td><td>هر شب جداگانه شمرده می‌شود؛ رزرو تا ۵ام و رزرو از ۵ام همدیگر را پر نمی‌کنند. رزرو لغوشده جا را آزاد می‌کند.</td></tr>
          <tr><td>تغییر رزرو</td><td>جابه‌جایی تاریخ، اتاق یا کانال و برگرداندن رزرو لغوشده دوباره با سهمیه سنجیده می‌شود. چک‌این و چک‌اوت هرگز به‌خاطر سهمیه‌ی کاهش‌یافته متوقف نمی‌شوند.</td></tr>
          <tr><td>API بیرونی</td><td>در جست‌وجوی اتاق‌های آزاد (<span class="mono">/rooms/availability</span>) پارامتر اختیاری <span class="mono">sales_channel_id</span> نوع‌هایی را که سهمیه‌ی آن کانال پر شده نشان نمی‌دهد؛ ثبت رزرو روی سهمیه‌ی پر خطای ۴۰۹ می‌دهد.</td></tr>
        </tbody>
      </table></div>
      <h3 id="ch-02-pricing">قیمت‌گذاری پیشرفته: روزهای هفته، نفر اضافه، اقامت طولانی و کد تخفیف</h3>
      <p>
        <b>چرا:</b> نرخ یک ویلا فقط «نرخ پایه + یک فصل» نیست. آخر هفته گران‌تر است، نفر اضافه هزینه دارد،
        اقامت طولانی تخفیف می‌خواهد و فصل شلوغ حداقل شب لازم دارد. همه‌ی این‌ها حالا یک جا حساب می‌شود و در
        صورت‌حساب رزرو، فرم رزرو و API دقیقاً یک عدد می‌دهد.
      </p>
      <ol class="steps">
        <li><b>۱.</b> <b>انواع اتاق/ویلا ← ویرایش:</b> «حداقل مدت اقامت»، «بزرگسالِ شاملِ نرخ پایه» و هزینه‌ی هر بزرگسال/کودک اضافه (در شب) را بگذارید. اگر «بزرگسالِ شامل» را خالی بگذارید نفر اضافه هزینه ندارد.</li>
        <li><b>۲.</b> <b>قیمت‌گذاری پویا:</b> هر قانون حالا می‌تواند فقط روی روزهای هفته‌ی انتخابی اثر کند (مثلاً پنجشنبه و جمعه ۳۰٪ بیشتر) و برای شب‌های خودش «حداقل شب» بخواهد.</li>
        <li><b>۳.</b> <b>تخفیف اقامت طولانی:</b> پله‌ها را بسازید، مثلاً از ۳ شب ۵٪ و از ۷ شب ۱۰٪. برای هر اقامت فقط بهترین پله اعمال می‌شود.</li>
        <li><b>۴.</b> <b>کدهای تخفیف رزرو:</b> کد، درصد یا مبلغ، بازه‌ی اعتبار، حداقل شب و سقف دفعات استفاده. در فرم رزرو کد را بنویسید؛ اعتبارش همان‌جا بررسی می‌شود و «برآورد قیمت» بالای فرم عدد نهایی را نشان می‌دهد.</li>
      </ol>
      <div class="tbl"><table class="ref">
        <thead><tr><th>قاعده</th><th>توضیح</th></tr></thead>
        <tbody>
          <tr><td>ترتیب حساب</td><td>نرخ شب‌ها (پایه + قانون) ← تخفیف اقامت طولانی روی شب‌های اتاق ← کد تخفیف روی مبلغ پس از آن تخفیف. نفر اضافه جدا اضافه می‌شود و تخفیف روی آن نیست.</td></tr>
          <tr><td>صورت‌حساب</td><td>اقامت ساده همان یک ردیف همیشگی است. نفر اضافه یک ردیف مثبت و هر تخفیف یک ردیف منفی جدا می‌گیرد؛ همه در «مانده» و «کش‌بک» به حساب می‌آیند.</td></tr>
          <tr><td>حداقل اقامت</td><td>بزرگ‌ترین عدد میان حداقل نوع اتاق و حداقل قانون‌هایی که شب‌های اقامت را می‌پوشانند. رزرو کوتاه‌تر رد می‌شود و در API هم اتاق پیشنهاد نمی‌شود.</td></tr>
          <tr><td>کد تخفیف</td><td>فعال بودن، بازه، حداقل شب و سقف دفعات هنگام ثبت بررسی می‌شود؛ شمارنده‌ی استفاده با ثبت رزرو بالا می‌رود. لغو رزرو شمارنده را برنمی‌گرداند.</td></tr>
          <tr><td>API بیرونی</td><td>جست‌وجوی اتاق خالی پارامترهای اختیاری <span class="mono">adults</span>، <span class="mono">children</span> و <span class="mono">promo_code</span> دارد و برای هر اتاق شیء <span class="mono">quote</span> (جمع شب‌ها، نفر اضافه، تخفیف‌ها و <span class="mono">total</span>) برمی‌گرداند. ثبت رزرو <span class="mono">promo_code</span> می‌گیرد؛ کد نامعتبر خطای ۴۲۲ <span class="mono">INVALID_PROMO_CODE</span> است.</td></tr>
        </tbody>
      </table></div>
      <h3 id="ch-02-reports">گزارش‌های هتلداری: اشغال، ADR و RevPAR</h3>
      <p>
        <b>چرا:</b> «این ماه چقدر فروختیم؟» کافی نیست. مدیر هتل با سه عدد تصمیم می‌گیرد: چند درصد اتاق‌ها پر بود
        (اشغال)، هر شب به‌طور میانگین چند تومان فروختیم (ADR) و هر اتاقِ در دسترس چقدر درآمد آورد (RevPAR).
        RevPAR همان اشغال × ADR است و نشان می‌دهد گران‌کردن یا تخفیف‌دادن واقعاً به نفع بوده یا نه.
      </p>
      <ol class="steps">
        <li><b>۱.</b> <b>اقامتی و هتلداری ← گزارش‌های هتلداری.</b> بازه را بالای صفحه انتخاب کنید (این ماه، ماه گذشته، ۳۰ روز اخیر، ۳۰ روز آینده، از ابتدای سال).</li>
        <li><b>۲.</b> کارت‌های بالا اشغال، ADR، RevPAR، درآمد اقامت، لغو، عدم حضور و جریمه‌ی لغو را نشان می‌دهند.</li>
        <li><b>۳.</b> دو جدول «به تفکیک کانال فروش» و «به تفکیک منبع رزرو» سهم هر کانال از درآمد و ADR آن را می‌دهند؛ برای تصمیم درباره‌ی کمیسیون و سهمیه به کار می‌آید.</li>
        <li><b>۴.</b> نمودار «پیش‌بینی ۳۰ روز آینده» برای هر روز رزروهای قطعی (ستون پر) و «در انتظار» (کم‌رنگ) را نسبت به واحدهای فعال نشان می‌دهد؛ روزهای خالی را می‌بینید و می‌توانید برایشان تخفیف یا کد بگذارید.</li>
      </ol>
      <div class="tbl"><table class="ref">
        <thead><tr><th>قاعده</th><th>توضیح</th></tr></thead>
        <tbody>
          <tr><td>چه چیزی فروش حساب می‌شود</td><td>رزروهای قطعی، چک‌این‌شده و چک‌اوت‌شده. «در انتظار» و «لغوشده» فروش نیستند.</td></tr>
          <tr><td>درآمد هر شب</td><td>کل صورت‌حساب رزرو (پس از تخفیف و با نفر اضافه) به‌طور مساوی بین شب‌هایش پخش می‌شود؛ اقامتی که از بازه بیرون می‌زند فقط برای شب‌های داخل بازه شمرده می‌شود.</td></tr>
          <tr><td>شب‌اتاق در دسترس</td><td>واحدهای فعال × تعداد شب‌های بازه. واحد غیرفعال حساب نمی‌شود.</td></tr>
          <tr><td>نرخ لغو</td><td>لغوشده‌ها (شامل عدم حضور) تقسیم بر همه‌ی رزروهایی که ورودشان در بازه است. جریمه‌ی لغو جدا از درآمد اقامت نشان داده می‌شود.</td></tr>
        </tbody>
      </table></div>
      <h3 id="ch-02-online-payment">پرداخت آنلاین رزرو، نگه‌داشتن موقت و چاپ صورت‌حساب</h3>
      <p>
        <b>چرا:</b> مهمانی که دور است نمی‌تواند پیش‌پرداخت نقد بدهد و رزرو بی‌پرداخت یا اتاق را بی‌دلیل می‌بندد یا
        می‌ریزد. حالا برای هر رزرو یک لینک پرداخت می‌فرستید، تا پرداخت نشده می‌توانید رزرو را با مهلت نگه دارید و
        بعد صورت‌حساب را چاپ کنید.
      </p>
      <ol class="steps">
        <li><b>۱.</b> درگاه پرداخت (زرین‌پال) را از «تنظیمات درگاه پرداخت» فعال کنید؛ همان درگاهی که شارژ آنلاین کیف پول استفاده می‌کند.</li>
        <li><b>۲.</b> در فهرست رزروها روی ردیف <b>«لینک پرداخت»</b> بزنید، مبلغ را بنویسید (پیش‌فرض کل مانده) و اگر می‌خواهید «پیامک هم بکن» را روشن بگذارید. لینک نمایش داده می‌شود و همان مبلغ دوباره لینک تازه نمی‌سازد.</li>
        <li><b>۳.</b> مهمان لینک را بدون ورود به سامانه باز می‌کند، مشخصات رزرو و مبلغ را می‌بیند و به درگاه می‌رود. پس از تأیید خودِ درگاه، پرداخت با روش «آنلاین» و شمارهٔ پیگیری در «پرداخت‌ها»ی رزرو ثبت می‌شود؛ یک لینک فقط یک بار پرداخت می‌شود.</li>
        <li><b>۴.</b> برای نگه‌داشتن موقت: هنگام ثبت رزرو «در انتظار تأیید»، مهلت (۶ تا ۷۲ ساعت) را بگذارید. اگر تا پایان مهلت هیچ پولی (نقد، آنلاین یا کیف پول) نیامد، رزرو بدون جریمه لغو و اتاق آزاد می‌شود؛ بررسی هر ۱۰ دقیقه است.</li>
        <li><b>۵.</b> برای چاپ: روی ردیف <b>«چاپ صورت‌حساب»</b> بزنید؛ اقلام، پرداخت‌ها، استردادها و مانده در یک برگ A4 می‌آید.</li>
      </ol>
      <div class="tbl"><table class="ref">
        <thead><tr><th>قاعده</th><th>توضیح</th></tr></thead>
        <tbody>
          <tr><td>اعتبار لینک</td><td>۳ روز. لینک منقضی یا پرداخت‌شده دیگر پرداخت نمی‌گیرد و باید لینک تازه ساخت.</td></tr>
          <tr><td>سقف مبلغ</td><td>مبلغ لینک از مانده‌ی صورت‌حساب بیشتر نمی‌شود. اگر پس از ساخت لینک، پذیرش بخشی را نقد گرفت و مهمان بیشتر از مانده پرداخت کرد، سقف مانده به رزرو می‌رود و مازاد خودکار به کیف پول مهمان برمی‌گردد.</td></tr>
          <tr><td>رزرو نگه‌داشته‌شده</td><td>پرداخت موفق لینک، رزرو «در انتظار تأیید» را «قطعی» می‌کند و مهلت را برمی‌دارد. هر تغییر وضعیت دیگر هم مهلت را برمی‌دارد.</td></tr>
          <tr><td>رزرو دارای پول</td><td>اگر مهلت تمام شود ولی پولی آمده باشد، رزرو لغو نمی‌شود؛ فقط مهلت برداشته می‌شود تا شما تصمیم بگیرید.</td></tr>
          <tr><td>تأیید پرداخت</td><td>فقط پاسخ خودِ درگاه معتبر است، نه بازگشت مهمان به سایت. اگر درگاه در دسترس نباشد، لینک «در انتظار» می‌ماند و بازکردن دوبارهٔ صفحه تأیید را دوباره امتحان می‌کند.</td></tr>
        </tbody>
      </table></div>
      <h3 id="ch-02-calendar">تقویم اشغال (نمای نواری اتاق‌ها)</h3>
      <p>
        <b>چرا:</b> «رک اتاق» فقط امروز را نشان می‌دهد. برای پاسخ به «آخر هفته‌ی بعد ویلای شمالی خالی است؟» باید چند هفته را
        یک‌جا ببینید. تقویم اشغال هر اتاق را یک ردیف و هر شب را یک ستون می‌کند و رزروها را به‌صورت نوار روی شب‌هایشان می‌کشد.
      </p>
      <ol class="steps">
        <li><b>۱.</b> <b>اقامتی و هتلداری ← تقویم اشغال.</b> پیش‌فرض از امروز و ۱۴ روز است.</li>
        <li><b>۲.</b> با «قبلی / بعدی» نصف بازه جابه‌جا می‌شود و «امروز» برمی‌گرداند؛ بازه (۷ تا ۶۰ روز) و نوع اتاق را بالای صفحه عوض کنید.</li>
        <li><b>۳.</b> رنگ نوار وضعیت را نشان می‌دهد: زرد در انتظار تأیید، آبی قطعی، سبز مقیم، خاکستری خارج‌شده. رزرو لغوشده نشان داده نمی‌شود چون اتاق را نگه نمی‌دارد.</li>
        <li><b>۴.</b> روی نوار بزنید تا همان رزرو باز شود. روی خانه‌ی خالی بزنید تا فرم رزرو تازه با <b>همان اتاق و همان شب</b> پر شده باز شود (تاریخ خروج فردای آن شب است و عوض‌کردنش آزاد است).</li>
        <li><b>۵.</b> ردیف پایین «پر / کل» تعداد اتاق‌های پر هر شب را می‌دهد؛ شبی که همه پر باشد قرمز می‌شود.</li>
      </ol>
      <p>
        رزروی که از بازه بیرون می‌زند در لبه‌ی بازه بریده می‌شود (لبه‌ی گوشه‌دار نشان می‌دهد ادامه دارد). دو رزرو پشت‌سرهم
        (خروج یکی همان روزی که ورود دیگری است) کنار هم می‌نشینند. تقویم فقط نمایش و باز کردن است؛ جابه‌جایی رزرو همچنان از
        ویرایش رزرو انجام می‌شود.
      </p>
      <h3 id="ch-02-change">تمدید اقامت و تغییر اتاق</h3>
      <p>
        <b>چرا:</b> مهمان می‌خواهد یک شب بیشتر بماند یا اتاقش مشکل دارد. تا امروز باید رزرو را لغو و دوباره ثبت می‌کردید
        و صورت‌حساب به‌هم می‌ریخت. حالا هر دو کار یک دکمه روی ردیف رزرو است.
      </p>
      <ol class="steps">
        <li><b>۱.</b> <b>«تمدید اقامت»:</b> تاریخ خروج تازه را بدهید؛ هزینه‌ی شب‌های اضافه (با نرخ همان شب‌ها و هزینه‌ی نفر اضافه) همان‌جا نشان داده می‌شود. اتاق باید برای آن شب‌ها آزاد باشد و سهمیه‌ی کانال هم رعایت می‌شود، وگرنه تمدید انجام نمی‌شود و پیام دلیل را می‌گوید. شب‌های اضافه یک ردیف جدا در صورت‌حساب می‌شوند.</li>
        <li><b>۲.</b> <b>«تغییر اتاق»:</b> فقط اتاق‌هایی که برای <b>همه‌ی شب‌های</b> این رزرو آزادند فهرست می‌شوند. اگر نوع اتاق فرق کند، «مابه‌التفاوت» را می‌بینید و می‌توانید با «تفاوت قیمت در صورت‌حساب بیاید» آن را اعمال یا برای ارتقای رایگان خاموش کنید. تفاوت منفی (اتاق ارزان‌تر) هم به‌صورت کاهش در صورت‌حساب می‌آید.</li>
        <li><b>۳.</b> برای مهمان مقیم، اتاق قبلی «نیازمند نظافت» و اتاق تازه «اشغال» می‌شود.</li>
      </ol>
      <p>
        هر دو کار برای رزرو «در انتظار»، «قطعی» و «مقیم» ممکن است و در سابقه‌ی فعالیت ثبت می‌شوند. تغییر اتاق کل رزرو را جابه‌جا
        می‌کند، پس برای مهمان مقیم اتاق تازه باید از ورود تا خروج آزاد باشد. کوتاه‌کردن اقامت همچنان با چک‌اوت زودهنگام انجام می‌شود.
      </p>
      <h3 id="ch-02-deposit">ودیعه‌ی خسارت (برای ویلا)</h3>
      <p>
        <b>چرا:</b> ویلا و سوئیت معمولاً ودیعه‌ای برای خسارت احتمالی می‌گیرند. این پول درآمد نیست و از مانده‌ی مهمان کم نمی‌شود؛
        هنگام خروج یا کامل برمی‌گردد یا بخشی از آن بابت خسارت نگه داشته می‌شود. اگر با «ثبت پرداخت» واردش کنید، هم درآمد را
        بالا نشان می‌دهد و هم مانده را به‌هم می‌ریزد؛ برای همین جداست.
      </p>
      <ol class="steps">
        <li><b>۱.</b> روی ردیف رزرو <b>«دریافت ودیعه»</b>: مبلغ و روش (نقد، کارت...). تا قبل از تسویه می‌توان چند بار مبلغ اضافه کرد؛ جمع نزد ما می‌ماند.</li>
        <li><b>۲.</b> هنگام خروج، پنجره‌ی چک‌اوت و «ورود و خروج امروز» یادآوری می‌کنند که ودیعه‌ی تسویه‌نشده دارید.</li>
        <li><b>۳.</b> <b>«تسویه ودیعه»</b>: مبلغ کسر بابت خسارت (صفر = کل ودیعه برگردد) و توضیح. سامانه می‌گوید چقدر باید به مهمان پس بدهید. تسویه فقط یک بار انجام می‌شود.</li>
        <li><b>۴.</b> اگر رزرو لغو شود، ودیعه‌ی گرفته‌شده خودکار «کامل برگشت‌شده» ثبت می‌شود (مبلغ را به مهمان پس بدهید).</li>
      </ol>
      <p>
        ودیعه در «چاپ صورت‌حساب» جدا از اقلام می‌آید (دریافت‌شده، کسر بابت خسارت، برگشت‌داده‌شده) و در پاسخ API رزرو با کلید
        <span class="mono">damage_deposit</span> هست. برگرداندن پول نقد را خودِ پذیرش انجام می‌دهد؛ سامانه فقط ثبت و محاسبه می‌کند.
      </p>
      <h3 id="ch-02-precheckin">پیش‌ثبت‌نام آنلاین مهمان</h3>
      <p>
        <b>چرا:</b> هنگام ورود، وقت پذیرش صرف نوشتن کد ملی و نام همراهان می‌شود. حالا مهمان همین‌ها را از قبل، از موبایل خودش پر
        می‌کند و شما فقط تأیید و چک‌این می‌کنید.
      </p>
      <ol class="steps">
        <li><b>۱.</b> روی ردیف رزرو <b>«لینک پیش‌ثبت‌نام»</b> بزنید. لینک نمایش داده می‌شود و می‌توانید پیامکش هم بکنید. لینک هر رزرو همیشه همان یکی است. (در «پیام‌های رزرو» هم می‌شود <span class="mono">{precheckin_link}</span> را در متن پیامک تأیید یا یادآوری گذاشت تا خودکار برود.)</li>
        <li><b>۲.</b> مهمان بدون ورود به سامانه باز می‌کند و کد ملی (الزامی، ۱۰ رقم؛ ارقام فارسی هم پذیرفته می‌شود)، ساعت تقریبی رسیدن، همراهان (تا ۱۰ نفر) و درخواست ویژه را می‌نویسد. تا پیش از ورود می‌تواند دوباره ویرایش کند.</li>
        <li><b>۳.</b> پاسخ را در «ورود و خروج امروز» (روی ردیف مهمان) و در ویرایش رزرو (بخش «پیش‌ثبت‌نام آنلاین مهمان») می‌بینید. اگر هنوز پر نکرده، روی ردیف ورود نوشته می‌شود تا بپرسید.</li>
      </ol>
      <p>
        مهمان فقط اطلاعات می‌افزاید؛ تاریخ، قیمت و مشخصات تماس رزرو را نمی‌تواند عوض کند. کد ملی فقط وقتی به پروندهٔ مهمان
        نسخه می‌شود که پروندهٔ او کد ملی نداشته باشد و کد در پرونده‌ی دیگری نباشد؛ وگرنه فقط در همین پیش‌ثبت‌نام می‌ماند. فرم برای رزرو
        «چک‌این‌شده» یا «لغوشده» بسته است.
      </p>
      <h3 id="ch-02-groups">رزرو گروهی (چند اتاق با هم)</h3>
      <p>
        <b>چرا:</b> یک خانواده‌ی سه‌ویلایی یا یک تور را سه بار جدا ثبت می‌کردید و حساب‌کتاب را دستی جمع می‌زدید. حالا یک «گروه» می‌سازید؛
        هر اتاق همان رزرو معمولی می‌ماند (چک‌این، تمدید، تغییر اتاق، ودیعه و لغو هرکدام جدا کار می‌کنند) و گروه فقط جمع‌ها،
        یک پرداخت مشترک و یک صورت‌حساب را اضافه می‌کند.
      </p>
      <ol class="steps">
        <li><b>۱.</b> <b>اقامتی و هتلداری ← رزروهای گروهی ← رزرو گروهی تازه.</b> مهمان مسئول، تاریخ ورود و خروج، وضعیت و منبع را بدهید و در «اتاق‌ها» هر اتاق (فقط اتاق‌های آزاد همان تاریخ‌ها فهرست می‌شوند) را با تعداد بزرگسال و کودکش اضافه کنید. یک اتاق را دو بار نمی‌توان انتخاب کرد.</li>
        <li><b>۲.</b> همه‌ی اتاق‌ها در یک مرحله ثبت می‌شوند؛ اگر یکی از اتاق‌ها همان لحظه گرفته شده باشد یا سهمیه‌ی کانال پر باشد، هیچ‌کدام ثبت نمی‌شود و پیام دلیل را می‌گوید.</li>
        <li><b>۳.</b> صفحه‌ی گروه جمع صورت‌حساب، پرداخت‌شده و مانده‌ی کل و فهرست اتاق‌ها را دارد؛ از هر ردیف به خود رزرو می‌روید.</li>
        <li><b>۴.</b> <b>«ثبت پرداخت گروهی»:</b> مبلغ را یک بار می‌گیرید و به ترتیب تاریخ ورود بین اتاق‌ها پخش می‌شود؛ هر اتاق تا مانده‌ی خودش می‌گیرد و از مانده‌ی کل گروه بیشتر ثبت نمی‌شود. در «پرداخت‌ها»ی هر رزرو ردیف جدا با یادداشت «پرداخت گروهی» می‌بینید.</li>
        <li><b>۵.</b> <b>«چاپ صورت‌حساب گروه»:</b> اقلام همه‌ی اتاق‌ها، پرداخت‌ها و مانده در یک برگ A4.</li>
        <li><b>۶.</b> <b>«لغو همه‌ی اتاق‌ها»:</b> هر اتاقِ «در انتظار» یا «قطعی» با شرایط لغو خودش لغو می‌شود؛ اتاقِ مقیم یا خارج‌شده دست نمی‌خورد.</li>
      </ol>
      <p>
        در فهرست رزروها می‌توانید ستون «گروه» را روشن کنید. تخفیف گروهی خودکار و استرداد گروهی ندارد؛ استرداد و کارهای دیگر
        را از خود رزرو هر اتاق انجام دهید. کد تخفیف و نگه‌داشتن موقت هم در این فرم نیست و از ثبت تک‌رزرو می‌آید.
      </p>
      <h3 id="ch-02-services">خدمات و هزینه‌های جانبی (فهرست آماده)</h3>
      <p>
        <b>چرا:</b> صبحانه، شام، لباسشویی و مینی‌بار را هر بار باید دستی با نام و قیمت تایپ می‌کردید؛ غلط‌املایی و قیمت‌های
        ناهماهنگ از همین‌جا می‌آمد. حالا یک بار فهرست می‌سازید و هر خدمت با یک انتخاب روی صورت‌حساب می‌نشیند.
      </p>
      <ol class="steps">
        <li><b>۱.</b> <b>اقامتی و هتلداری ← خدمات و هزینه‌های جانبی ← ایجاد:</b> نام، قیمت هر واحد و ترتیب نمایش.</li>
        <li><b>۲.</b> در فهرست رزروها روی ردیف <b>«افزودن خدمت»</b> بزنید (برای رزرو «در انتظار»، «قطعی» و «مقیم»)، خدمت و تعداد را انتخاب کنید. همین دکمه در بخش «اقلام صورتحساب» خود رزرو هم هست؛ «ایجاد» همچنان برای قلم تک‌باره با قیمت دلخواه است.</li>
        <li><b>۳.</b> قلم روی صورت‌حساب می‌آید و در مانده، چاپ صورت‌حساب و گزارش‌ها حساب می‌شود.</li>
      </ol>
      <p>
        نام و قیمت هنگام افزودن روی همان رزرو <b>کپی</b> می‌شود؛ تغییر یا غیرفعال‌کردن خدمت در فهرست، صورت‌حساب‌های نوشته‌شده را
        عوض نمی‌کند. خدمت غیرفعال در فهرست انتخاب نمی‌آید. مالیات و عوارض هنوز به‌صورت خودکار روی صورت‌حساب نمی‌آید.
      </p>
      <h3 id="ch-02-night-audit">بستن روز (ممیزی شبانه)</h3>
      <p>
        <b>چرا:</b> آخر هر روز پذیرش باید مطمئن شود چیزی جا نمانده: مهمانی که نیامد، مهمانی که باید می‌رفت، مانده‌ای که نگرفته‌اید،
        ودیعه‌ای که برنگردانده‌اید، اتاقی که وضعیتش غلط است، و پولی که صندوق باید نشان دهد. این‌ها در یک صفحه جمع شده‌اند.
      </p>
      <ol class="steps">
        <li><b>۱.</b> <b>اقامتی و هتلداری ← بستن روز.</b> بالای صفحه تاریخ است (با «روز قبل» و «روز بعد» می‌توانید روزهای گذشته را ببینید).</li>
        <li><b>۲.</b> «گردش پول این روز» جمع پرداخت‌ها و پیش‌پرداخت‌ها منهای استردادهاست، به تفکیک روش (نقد، کارت...)؛ همان عددی که باید با صندوق بخواند.</li>
        <li><b>۳.</b> هر بخش موارد باز را با نام مهمان و پیوند به رزرو نشان می‌دهد. «مهمانی که نیامده» دکمه‌ی <b>«عدم حضور»</b> دارد که طبق سیاست لغو همان رزرو جریمه را حساب می‌کند.</li>
        <li><b>۴.</b> <b>«بستن روز»:</b> برای هر روز یک بار. ثبت می‌شود چه کسی و چه زمانی بست، و نسخه‌ی ثابتی از گردش پول، اشغال و تعداد موارد باز می‌ماند. موارد باز جلوی بستن را نمی‌گیرند (گاهی پذیرش مجبور است با موردی باز ببندد) ولی تعدادشان ثبت می‌شود. روز آینده بسته نمی‌شود.</li>
      </ol>
      <div class="tbl"><table class="ref">
        <thead><tr><th>بخش</th><th>چه چیزی نشان می‌دهد</th></tr></thead>
        <tbody>
          <tr><td>مهمانی که نیامده</td><td>رزرو «در انتظار» یا «قطعی» که روز ورودش (تا همین روز) رسیده و چک‌این نشده.</td></tr>
          <tr><td>مقیم دیرکرده / خروج امروز</td><td>چک‌این‌شده‌ای که روز خروجش گذشته، و آنکه امروز باید برود و هنوز چک‌اوت نشده.</td></tr>
          <tr><td>مانده</td><td>رزرو مقیم یا در ۳۰ روز اخیر خارج‌شده که هنوز مانده دارد.</td></tr>
          <tr><td>ودیعه‌ی تسویه‌نشده</td><td>ودیعه‌ی خسارت برای اقامتی که تمام شده یا امروز باید تمام شود.</td></tr>
          <tr><td>اتاق نامطابق</td><td>اتاق «اشغال» بدون مهمان چک‌این‌شده، یا مهمان چک‌این‌شده در اتاقی که اشغال نیست.</td></tr>
        </tbody>
      </table></div>
      <h3 id="ch-02-rate-plans">نرخ‌های فروش (با صبحانه، بدون صبحانه، غیرقابل بازگشت)</h3>
      <p>
        <b>چرا:</b> یک اتاق را معمولاً چند جور می‌فروشند: با صبحانه کمی گران‌تر، «غیرقابل بازگشت» ارزان‌تر ولی با جریمه‌ی کامل.
        به‌جای ساختن اتاق یا قانون قیمت جدا، یک «نرخ فروش» تعریف می‌کنید که نرخ همان اتاق را جابه‌جا می‌کند.
      </p>
      <ol class="steps">
        <li><b>۱.</b> <b>اقامتی و هتلداری ← نرخ‌های فروش ← ایجاد:</b> نام (مثلاً «با صبحانه»)، نوع تغییر (درصد یا مبلغ ثابت در هر شب؛ عدد منفی یعنی تخفیف) و در صورت نیاز یک <b>سیاست لغو مخصوص</b> (برای «غیرقابل بازگشت» سیاستی با جریمه‌ی ۱۰۰٪).</li>
        <li><b>۲.</b> در فرم رزرو، «نرخ فروش» را انتخاب کنید (خالی = نرخ معمول). «برآورد قیمت» همان‌جا قیمت را با نرخ انتخابی نشان می‌دهد.</li>
        <li><b>۳.</b> نرخ فروش روی <b>هر شب</b> اعمال می‌شود، بعد از قانون‌های قیمت‌گذاری پویا (تاریخ و روز هفته)؛ پس «با صبحانه +۱۰٪» در نوروز هم +۱۰٪ همان نرخ نوروز است. هیچ شبی کمتر از صفر نمی‌شود.</li>
        <li><b>۴.</b> نام نرخ کنار ردیف اقامت در صورت‌حساب می‌آید. تمدید اقامت و تغییر اتاق هم با همان نرخ حساب می‌شوند.</li>
      </ol>
      <p>
        سیاست لغو به‌ترتیب از این‌ها گرفته می‌شود: انتخاب صریح در فرم رزرو، بعد سیاست خود نرخ فروش، بعد سیاست پیش‌فرض. در API بیرونی،
        جست‌وجوی اتاق خالی پارامتر اختیاری <span class="mono">rate_plan_id</span> دارد (قیمت‌ها با آن نرخ حساب می‌شود) و ثبت رزرو
        <span class="mono">rate_plan_id</span> می‌گیرد؛ نرخ غیرفعال رد می‌شود. نرخ فروش به رزروهای قبلی اثری ندارد و در فرم رزرو گروهی نیامده است.
      </p>
      <h3 id="ch-02-waitlist">لیست انتظار اتاق</h3>
      <p>
        <b>چرا:</b> وقتی مهمانی اتاق می‌خواهد و همه‌چیز پر است، معمولاً شماره‌اش روی کاغذ می‌ماند و وقتی یک لغو پیش می‌آید یا کسی
        یادش نیست یا دیر خبر می‌دهد. حالا ثبتش می‌کنید و سامانه خودش به‌محض آزادشدن اتاق پیامک می‌زند.
      </p>
      <ol class="steps">
        <li><b>۱.</b> <b>اقامتی و هتلداری ← لیست انتظار اتاق ← ایجاد:</b> مهمان (موبایلش لازم است)، تاریخ ورود و خروج، تعداد نفرات و «نوع اتاق دلخواه» (خالی = هر نوعی).</li>
        <li><b>۲.</b> هر رزرو که لغو شود (دستی، عدم حضور یا پایان مهلت نگه‌داشتن)، سامانه بررسی می‌کند آیا یک اتاق از نوع دلخواه برای <b>همه‌ی شب‌های</b> مهمان منتظر آزاد شده است. اگر بله، پیامک «اتاق خالی شد» به او می‌رود و وضعیتش «اطلاع داده شد» می‌شود؛ هر درخواست فقط یک بار پیامک می‌گیرد.</li>
        <li><b>۳.</b> پیامک اتاق را برای مهمان <b>نگه نمی‌دارد</b>؛ پذیرش باید با او تماس بگیرد و رزرو کند، بعد «رزرو شد» را بزند. «بستن» برای درخواستی است که دیگر لازم نیست.</li>
        <li><b>۴.</b> ستون «اتاق الان آزاد» نشان می‌دهد برای درخواست‌های منتظر هم‌اکنون اتاقی هست یا نه؛ دکمه‌ی «اطلاع بده» پیامک را دستی هم می‌فرستد.</li>
      </ol>
      <p>
        اگر سرویس پیامک خاموش باشد یا مهمان شماره نداشته باشد، پیامک ارسال‌نشده در مکالمات مهمان با دلیلش ثبت می‌شود و درخواست «در انتظار» می‌ماند
        تا بعداً دستی خبر بدهید. درخواستی که روز ورودش گذشته دیگر خودکار پیامک نمی‌گیرد.
      </p>
      <h3 id="ch-02-guest-flags">هشدار پذیرش و مسدودکردن مهمان</h3>
      <p>
        <b>چرا:</b> اینکه فلان مهمان مهمان ویژه است یا قبلاً مشکل پرداخت داشته معمولاً فقط در ذهن یکی از همکاران است. حالا روی پرونده‌ی خود مهمان ثبت می‌شود.
      </p>
      <ol class="steps">
        <li><b>۱.</b> در ویرایش مهمان، «هشدار برای پذیرش» را بنویسید؛ در فهرست رزروها کنار رزروهای او یک مثلث هشدار نارنجی می‌آید (با نگه‌داشتن موس متن را می‌بینید) و هنگام زدن «چک‌این» در پنجره‌ی تأیید نشان داده می‌شود.</li>
        <li><b>۲.</b> «مسدود برای رزرو» را بزنید تا رزرو جدید برای او از هیچ مسیری (پنل، اپ مشتری، کانال‌ها) ثبت نشود. رزروهای قبلی دست نمی‌خورند و با برداشتن همان تیک دوباره رزرو ممکن می‌شود.</li>
      </ol>
      <h3 id="ch-02-room-rack">رک اتاق (وضعیت زنده)</h3>
      <p>
        <b>چرا:</b> پذیرش باید با یک نگاه بداند کدام واحد خالی است، کدام مهمان دارد، کدام نظافت می‌خواهد و امروز چه کسی می‌آید یا می‌رود.
      </p>
      <ol class="steps">
        <li><b>۱.</b> بالای صفحه چیپ‌های فیلتر با شمارنده است: همه، خالی و آماده، مقیم، نیازمند نظافت، خارج از سرویس، ورود امروز، خروج امروز. با جست‌وجو هم می‌شود واحد یا نام مهمان را پیدا کرد.</li>
        <li><b>۲.</b> واحدها بر اساس نوع گروه‌بندی شده‌اند. هر کارت وضعیت (با رنگ و متن)، مهمان مقیم با «شب چندم از چند» و نوار پیشرفت اقامت، یا رزرو بعدی، یا دلیل بسته‌بودن واحد را نشان می‌دهد.</li>
        <li><b>۳.</b> نشانک‌های کارت: خروج امروز، ورود امروز، گذشتن از ساعت خروج، مانده‌ی حساب، هشدار پذیرش و مهمان مسدود.</li>
        <li><b>۴.</b> روی کارت واحدی که «نیازمند نظافت» است دکمه‌ی <b>«نظافت انجام شد»</b> هست و آن را به «خالی و آماده» برمی‌گرداند (نیاز به دسترسی ویرایش اتاق دارد).</li>
        <li><b>۵.</b> صفحه خودش هر ۳۰ ثانیه تازه می‌شود؛ لازم نیست دوباره بارگذاری کنید. فیلتر و جست‌وجو در آدرس می‌ماند و می‌شود لینکش را برای همکار فرستاد.</li>
      </ol>
      <h3 id="ch-02-morning-digest">خلاصه‌ی صبحگاهی پذیرش</h3>
      <p>
        هر صبح ساعت ۸:۰۰ به مدیر و کسانی که به «ورود و خروج امروز» دسترسی دارند یک اعلان (و پوش، اگر روشن کرده‌اند) می‌رسد:
        تعداد ورودی‌ها، خروجی‌ها، مهمان‌های مقیم، اتاق‌های خارج از سرویس، یادداشت‌های باز تحویل شیفت و پولی که دیروز دریافت شده.
        اگر نه ورودی هست، نه خروجی و نه مقیم، چیزی فرستاده نمی‌شود. اعلان به صفحه‌ی «ورود و خروج امروز» لینک است.
      </p>
      <h3 id="ch-02-balance-reminder">یادآوری مانده‌ی حساب</h3>
      <p>
        <b>چرا:</b> مهمان قطعی که قبل از ورود تسویه نکرده، روز ورود صف پذیرش را طولانی می‌کند یا اصلاً نمی‌آید. حالا چند روز قبل پیامک می‌گیرد.
      </p>
      <ol class="steps">
        <li><b>۱.</b> در <b>تنظیمات پیام‌های رزرو</b> گزینه‌ی «پیش از ورود، پیامک مانده‌ی حساب بفرست» را روشن کنید و تعداد روز را بدهید (پیش‌فرض ۳ روز). تا روشن نکنید هیچ پیامکی نمی‌رود.</li>
        <li><b>۲.</b> هر صبح ساعت ۹:۴۵ برای رزروهای قطعی که در این بازه می‌رسند و <b>مانده‌شان بیش از صفر است</b> پیامک می‌رود؛ هر رزرو فقط یک بار. رزرو تسویه‌شده پیامک نمی‌گیرد.</li>
        <li><b>۳.</b> در متن می‌توانید از <span class="mono">{balance}</span> (مانده به تومان) و بقیه‌ی متغیرها استفاده کنید. پیامک‌های ارسال‌نشده با دلیلشان در مکالمات مهمان می‌ماند.</li>
      </ol>
      <h3 id="ch-02-booking-search">جست‌وجوی رزرو</h3>
      <p>
        کادر جست‌وجوی فهرست رزروها حالا علاوه بر کد رزرو، <b>نام مهمان</b>، <b>موبایل او به هر شکل</b> (۰۹۱۲...، ‎+۹۸۹۱۲...، ارقام فارسی)،
        <b>کد ملی</b> و <b>نام اتاق/ویلا</b> را پیدا می‌کند؛ برای پیداکردن رزرو وقتی مهمان پشت تلفن فقط شماره‌اش را می‌گوید.
      </p>
      <h3 id="ch-02-booking-tabs">تب‌های فهرست رزروها</h3>
      <p>
        بالای فهرست رزروها شش تب است تا برای سؤال‌های همیشگی پذیرش فیلتر نزنید: <b>همه</b> (مثل قبل)، <b>ورود امروز</b>،
        <b>مقیم</b> (چک‌این‌شده‌ها)، <b>آینده</b>، <b>در انتظار تأیید</b> و <b>لغو شده</b>. فیلترها و جست‌وجو روی هر تب کار می‌کنند.
      </p>
      <h3 id="ch-02-late-checkout">دیرکرد در خروج</h3>
      <p>
        در بخش «خروج امروز» زیر هر مهمان ساعت خروجِ نوع اتاقش نوشته می‌شود؛ اگر آن ساعت گذشته و هنوز چک‌اوت نشده، با رنگ قرمز و «از ساعت خروج گذشته است» نشان داده می‌شود.
        برای دریافت هزینه‌ی دیرکرد، از دکمه‌ی «افزودن هزینه» روی رزرو (فهرست خدمات و هزینه‌های جانبی) استفاده کنید.
      </p>
      <h3 id="ch-02-housekeeping-sheet">برگه‌ی نظافت روزانه</h3>
      <p>
        <b>چرا:</b> نظافت‌چی‌ها با گوشی کار نمی‌کنند و هر صبح باید بدانند کدام اتاق را اول بروند. در صفحه‌ی «ورود و خروج امروز» دکمه‌ی <b>«برگه‌ی نظافت امروز»</b> یک برگه‌ی A4 قابل چاپ باز می‌کند:
      </p>
      <ol class="steps">
        <li><b>۱.</b> <b>بعد از خروج:</b> اتاق‌هایی که امروز مهمانشان می‌رود (نظافت کامل) با ساعت خروج.</li>
        <li><b>۲.</b> <b>آماده‌سازی برای ورود:</b> اتاق‌هایی که امروز مهمان می‌گیرند، با تعداد نفرات، ساعت رسیدن و درخواست مهمان از پیش‌ثبت‌نام.</li>
        <li><b>۳.</b> <b>مهمان مقیم:</b> اتاق‌هایی که مهمان می‌ماند، برای سرویس روزانه.</li>
      </ol>
      <p>هر ردیف یک خانه‌ی تیک برای کاغذ دارد. برای روز دیگر، به آدرس برگه <span class="mono">?date=2026-10-23</span> (تاریخ میلادی) اضافه کنید.</p>
      <h3 id="ch-02-desk-reminders">یادآوری‌های پذیرش در صفحه‌ی ورود و خروج امروز</h3>
      <p>
        بالای صفحه‌ی «ورود و خروج امروز» سه نوار هشدار نارنجی می‌آید (فقط وقتی تعدادشان بیش از صفر است و فقط اگر به آن بخش دسترسی دارید):
        یادداشت‌های باز دفتر تحویل شیفت، اتاق‌های خارج از سرویس امشب و اشیای جامانده‌ی تحویل‌نشده. روی هر نوار بزنید تا به فهرستش بروید.
      </p>
      <h3 id="ch-02-handover">دفتر تحویل شیفت</h3>
      <p>
        <b>چرا:</b> «مهمان اتاق ۱۲ ساعت ۶ صبح تاکسی می‌خواهد» یا «کولر ۴ تعمیرکار لازم دارد» معمولاً شفاهی یا روی کاغذ دست‌به‌دست می‌شود و گم می‌شود.
      </p>
      <ol class="steps">
        <li><b>۱.</b> <b>اقامتی و هتلداری ← دفتر تحویل شیفت ← ایجاد:</b> یادداشت را بنویسید و اگر به اتاق یا رزروی مربوط است انتخابش کنید. ثبت‌کننده و زمان خودکار ثبت می‌شود.</li>
        <li><b>۲.</b> فهرست به‌طور پیش‌فرض فقط یادداشت‌های باز را نشان می‌دهد؛ شیفت بعد با «انجام شد» آن را می‌بندد و زمان و بستن‌کننده ثبت می‌شود. با برداشتن فیلتر «فقط باز» سابقه دیده می‌شود.</li>
      </ol>
      <h3 id="ch-02-cleaning-link">نظافت و وضعیت اتاق: یک حلقه</h3>
      <p>
        <b>چرا:</b> قبلاً بسته‌شدن تسک نظافت یک جا و «نظافت انجام شد» روی رک جای دیگر بود؛ اتاقی که تمیز شده بود ممکن بود در رک هنوز کثیف بماند و برعکس.
      </p>
      <ol class="steps">
        <li><b>۱.</b> چک‌اوت مهمان، اتاق را «نیازمند نظافت» می‌کند و تسک نظافت (با چک‌لیست دپارتمان) می‌سازد. <b>تغییر اتاقِ مهمان مقیم</b> هم برای اتاقی که مهمان از آن رفته همین تسک را می‌سازد (قبلاً هیچ تسکی ساخته نمی‌شد).</li>
        <li><b>۲.</b> بستن تسک نظافت اتاق را «خالی و آماده» می‌کند؛ و دکمه‌ی «نظافت انجام شد» روی رک هم تسک باز نظافت همان اتاق را می‌بندد. هر کدام را بزنید، دیگری هم هماهنگ می‌شود.</li>
        <li><b>۳.</b> اگر تسک نظافت دیر بسته شود و اتاق در این فاصله مهمان تازه گرفته باشد (یا خارج از سرویس شده باشد)، وضعیتش دست نمی‌خورد.</li>
      </ol>
      <h3 id="ch-02-inventory">یک عدد برای همه: موجودی و اشغال</h3>
      <p>
        قبلاً هر صفحه خودش حساب می‌کرد چند واحد داریم و چندتایش پر است؛ نتیجه اینکه رک، داشبورد، گزارش‌ها و دستیار هوشمند ممکن بود عددهای متفاوت بدهند.
        حالا همه از یک محاسبه می‌خوانند:
      </p>
      <ol class="steps">
        <li><b>۱.</b> واحدی که مهمان چک‌این‌شده دارد <b>اشغال</b> است، حتی اگر وضعیتش را کسی دستی عوض نکرده باشد؛ و واحدی که وضعیتش «اشغال» مانده ولی کسی در آن نیست قابل فروش است.</li>
        <li><b>۲.</b> واحد <b>خارج از سرویس</b> فقط یک منبع دارد (فهرست «اتاق‌های خارج از سرویس»؛ وضعیت اتاق هم همان را می‌سازد) و از <b>فروش و ظرفیت</b> کم می‌شود: در جست‌وجوی اتاق خالی (پنل، اپ مشتری، کانال‌ها، دستیار هوشمند و لیست انتظار)، در درصد اشغال و RevPAR گزارش‌ها، در بستن روز، در داشبورد و در پیش‌بینی ۳۰ روزه.</li>
        <li><b>۳.</b> در تقویم اشغال، شب‌های خارج از سرویس با خط‌خطی قرمز نشان داده می‌شود و جمع پایین هر روز «پر / قابل فروش» است.</li>
        <li><b>۴.</b> در «ورود و خروج امروز» هشدار پذیرش و مسدودبودن مهمان هم کنار هر رزرو دیده می‌شود، نه فقط در پنجره‌ی چک‌این.</li>
      </ol>
      <h3 id="ch-02-room-blocks">اتاق‌های خارج از سرویس</h3>
      <p>
        <b>چرا:</b> اتاقی که کولرش خراب است یا رنگ می‌شود باید در همان روزها فروخته نشود، چه از پنل، چه از اپ مشتری و چه از کانال‌های فروش.
      </p>
      <ol class="steps">
        <li><b>۱.</b> <b>اقامتی و هتلداری ← اتاق‌های خارج از سرویس ← ایجاد:</b> اتاق، «از تاریخ»، «تا تاریخ» و دلیل را بنویسید. «تا تاریخ» روزِ آزادشدن است؛ خودِ آن روز دوباره قابل رزرو است.</li>
        <li><b>۲.</b> در آن بازه اتاق در رزرو جدید، تغییر اتاق، رزرو گروهی، لیست انتظار و موجودی کانال‌ها آزاد حساب نمی‌شود و رزرو دستی روی آن خطا می‌دهد.</li>
        <li><b>۳.</b> اگر در بازه رزرو فعالی روی اتاق باشد، بستن رد می‌شود؛ ابتدا اتاق آن رزرو را عوض یا لغو کنید. «تا تاریخ» را خالی بگذارید تا بسته‌شدن تا اطلاع بعدی ادامه داشته باشد.</li>
        <li><b>۴.</b> <b>یک منبع واحد:</b> اگر در فرم اتاق وضعیت را «خارج از سرویس» بزنید، خودکار همین‌جا یک ردیف بدون تاریخ پایان ساخته می‌شود؛ و اگر وضعیت را برگردانید، آن ردیف بسته می‌شود. دکمه‌ی <b>«برگرداندن به سرویس»</b> هم وضعیت اتاق را به «خالی و آماده» برمی‌گرداند. رک اتاق، رزرو، کانال‌ها و لیست انتظار همه از همین یک فهرست می‌خوانند.</li>
      </ol>
      <h3 id="ch-02-lost-found">اشیای جامانده</h3>
      <p>
        <b>چرا:</b> شارژر و عینک و لباسی که مهمان جا می‌گذارد معمولاً در کمد پذیرش گم می‌شود و کسی یادش نیست مال کیست. حالا هر مورد با صاحبش ثبت می‌شود و تا تحویل یا بایگانی پیگیری می‌شود.
      </p>
      <ol class="steps">
        <li><b>۱.</b> روی ردیف رزرو دکمه‌ی <b>«ثبت شیء جامانده»</b> را بزنید و شیء و محل پیداشدنش را بنویسید؛ مهمان و اتاق از رزرو برداشته می‌شود. بدون رزرو هم از <b>اقامتی و هتلداری ← اشیای جامانده ← ایجاد</b> می‌شود ثبت کرد.</li>
        <li><b>۲.</b> «اطلاع به مهمان» پیامک می‌فرستد و وضعیت را «به مهمان خبر داده شد» می‌کند. اگر پیامک نرفت، وضعیت عوض نمی‌شود.</li>
        <li><b>۳.</b> وقتی شیء به مهمان داده شد «تحویل داده شد» و اگر صاحبی پیدا نشد «بایگانی / دور ریخته شد» را بزنید (یادداشت اختیاری است). موارد بسته‌شده دیگر پیامک نمی‌گیرند.</li>
      </ol>
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
        <div class="feature-card"><div class="name">انواع بلیت</div><div class="desc">تعریف انواع بلیت هر محصول (بزرگسال، کودک و…) با قیمت جدا.</div></div>
        <div class="feature-card"><div class="name">سفارش‌ها و بلیت‌ها</div><div class="desc">فهرست همه‌ی سفارش‌های صادرشده با وضعیت پرداخت و بلیت‌هایشان.</div></div>
        <div class="feature-card"><div class="name">گزارش‌های گیشه</div><div class="desc">گزارش فروش یک بازه: خلاصه، به تفکیک محصول، صندوقدار، روش پرداخت، منبع فروش و کد تخفیف، بلیت‌های استفاده‌نشده، رایگان‌ها و ورودی ساعت‌به‌ساعت؛ با دکمه‌ی «خروجی اکسل» که برای هر بخش یک برگه می‌سازد.</div></div>
        <div class="feature-card"><div class="name">هشدار ورود مشکوک</div><div class="desc">اگر یک بلیط بارها در گیت رد شود (احتمالاً QR کپی‌شده)، مدیران هر چند دقیقه یک‌بار اعلان می‌گیرند.</div></div>
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
        <div class="feature-card"><div class="name">صندوق پیام</div><div class="desc">همه‌ی مکالمات مشتریان (ایمیل، پیامک، واتس‌اپ) در یک‌جا، به ترتیب آخرین فعالیت؛ پاسخ از همین‌جا با ایمیل یا پیامک. ایمیل دریافتی از صندوق ایمیل خوانده نمی‌شود؛ آنچه «دریافتی» دیده می‌شود پیام‌های واتس‌اپ است.</div></div>
        <div class="feature-card"><div class="name">بخش‌بندی مشتریان</div><div class="desc">فهرست‌های ذخیره‌شده که فقط قاعده‌شان نگه داشته می‌شود و هر بار تازه محاسبه می‌شوند.</div></div>
        <div class="feature-card"><div class="name">مشتریان تکراری</div><div class="desc">پرونده‌هایی که به احتمال زیاد یک نفرند (هم‌شماره موبایل، هم‌ایمیل، هم‌نام…) نشان داده می‌شوند و شما انتخاب می‌کنید کدام بماند؛ ادغام هرگز خودکار نیست. موبایل‌ها با هر املایی (۰۹۱۲…، ‎+98912…، ارقام فارسی) یکی حساب می‌شوند و دستور «guests:normalize-mobiles» املای قدیمی را یکدست می‌کند.</div></div>
        <div class="feature-card"><div class="name">ارزش و ریزش مشتری</div><div class="desc">ارزش واقعی هر مشتری از اقامت‌های تکمیل‌شده و وضعیت فعال، در معرض ریزش یا ازدست‌رفته؛ با دکمه‌ی ساخت وظیفه‌ی پیگیری برای مشتریان در معرض ریزش.</div></div>
        <div class="feature-card"><div class="name">کیف پول و کش‌بک</div><div class="desc">موجودی هر مشتری، درصد کش‌بک هر سطح وفاداری (برنزی، نقره‌ای، طلایی، VIP)، مدت اعتبار کش‌بک و یادآوری پیامکی پیش از انقضا از «تنظیمات کیف پول و کش‌بک»؛ و «گزارش بدهی کیف پول» که کل تعهد، اعتبار در آستانه‌ی انقضا و موجودی‌های ناهماهنگ با گردش را نشان می‌دهد.</div></div>
        <div class="feature-card"><div class="name">فرم جذب سرنخ</div><div class="desc">فرم عمومی بدون ورود در آدرس /lead/نام-فرم؛ هر ثبت، یک مشتری و یک معامله‌ی «سرنخ جدید» می‌سازد.</div></div>
        <div class="feature-card"><div class="name">اتوماسیون پایپ‌لاین</div><div class="desc">قانون «وقتی معامله وارد مرحله‌ی X شد، پیگیری‌ای N ساعت بعد بساز».</div></div>
        <div class="feature-card"><div class="name">پایپ‌لاین فروش (کانبان)</div><div class="desc">معاملات به‌صورت ستون‌های مرحله؛ هر معامله‌ی باز امتیاز ۰ تا ۱۰۰ با دلیل هر امتیاز دارد، و معاملاتی که دو هفته ساکت مانده‌اند هر صبح به صاحبشان یادآوری می‌شوند.</div></div>
        <div class="feature-card"><div class="name">تیکت‌های پشتیبانی و NPS</div><div class="desc">تیکت با مهلت پاسخ (SLA)؛ اولین بار که مهلت می‌گذرد به مسئول (یا همه‌ی مدیران) اعلان می‌رسد. بازخوردها و NPS در «بازخوردها و NPS» جمع می‌شوند.</div></div>
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
        <div class="feature-card"><div class="name">درگاه پرداخت آنلاین</div><div class="desc">شارژ آنلاین کیف پول از وب‌اپ مشتریان با زرین‌پال: روشن/خاموش، مرچنت‌کد، حالت آزمایشی و حداقل/حداکثر مبلغ. تا فعال نشود و مرچنت‌کد وارد نشود، دکمه‌ی شارژ دیده نمی‌شود.</div></div>
        <div class="feature-card"><div class="name">اکانت‌های API خارجی</div><div class="desc">کلید دسترسی هر سامانه‌ی بیرونی برای REST API؛ جزئیات در فصل REST API.</div></div>
        <div class="feature-card"><div class="name">دیپلوی سرور</div><div class="desc">درخواست دیپلوی (دریافت کد، مایگریشن، بازسازی کش) با یک کلیک؛ این صفحه فقط درخواست را ثبت می‌کند و کار اصلی را کرون سرور انجام می‌دهد.</div></div>
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
          <tr><td class="mono"><code>booking.confirmed</code></td><td>قطعی‌شدن رزرو (وضعیت به «رزرو قطعی» رسید)</td></tr>
          <tr><td class="mono"><code>booking.checked_in</code> / <code>booking.checked_out</code></td><td>چک‌این و چک‌اوت مهمان</td></tr>
          <tr><td class="mono"><code>booking.cancelled</code></td><td>لغو رزرو یا ثبت عدم حضور؛ شامل <span class="mono">cancellation_kind</span> و <span class="mono">cancellation_fee</span></td></tr>
          <tr><td class="mono"><code>booking.payment_recorded</code></td><td>ثبت پرداخت، پیش‌پرداخت یا استرداد؛ شامل مبلغ، <span class="mono">paid_amount</span>، <span class="mono">balance_due</span> و <span class="mono">payment_status</span></td></tr>
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

  <!-- ============ 14 — SEO CENTER ============ -->
  <section class="chapter" id="ch-14">
    <div class="chapter-head">
      <div class="chapter-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="7"/><path d="m20 20-4.35-4.35"/><path d="M6.5 11.5 9 9l2 2 3.5-3.5"/></svg>
      </div>
      <div>
        <span class="chapter-num">فصل ۱۴</span>
        <h2>مرکز سئو — جزوه‌ی کامل از تعریف پروژه تا گزارش</h2>
      </div>
    </div>
    <div class="chapter-body">
      <p class="lede">
        این فصل مثل جزوه‌ی کلاس نوشته شده است: اگر از اول تا آخر بخوانید و مرحله‌ها را به همان ترتیب انجام دهید، می‌توانید
        یک سایت را از صفر به سامانه وصل کنید، هر روز با آن کار کنید، نتیجه‌ی کارتان را اندازه بگیرید و همین روش را به
        همکارتان هم یاد بدهید. هر بخش سه چیز دارد: <b>چرا</b> این کار را می‌کنیم، <b>دقیقاً کجا و چه بزنیم</b>، و
        <b>چه چیزی باید ببینیم</b> تا بفهمیم درست شده است.
      </p>

      <ul class="subtoc">
        <li><a href="#seo-0">۰. تصویر کلی و واژه‌ها</a></li>
        <li><a href="#seo-1">۱. پیش‌نیازهای یک‌بارِ کل سامانه</a></li>
        <li><a href="#seo-2">۲. تعریف پروژه</a></li>
        <li><a href="#seo-3">۳. اتصال Search Console</a></li>
        <li><a href="#seo-4">۴. اتصال وردپرس (پلاگین)</a></li>
        <li><a href="#seo-5">۵. اتصال Google Analytics</a></li>
        <li><a href="#seo-6">۶. خواندن داده‌ها</a></li>
        <li><a href="#seo-7">۷. فرصت‌ها و صف اقدام</a></li>
        <li><a href="#seo-8">۸. کلمه‌ی کلیدی تازه</a></li>
        <li><a href="#seo-9">۹. محتوای سایت و برد سلامت</a></li>
        <li><a href="#seo-10">۱۰. خلأ پوشش و بریف</a></li>
        <li><a href="#seo-11">۱۱. تقویم محتوا</a></li>
        <li><a href="#seo-12">۱۲. ممیزی فنی</a></li>
        <li><a href="#seo-13">۱۳. هشدارها و پایش</a></li>
        <li><a href="#seo-14">۱۴. گزارش دوره‌ای</a></li>
        <li><a href="#seo-15">۱۵. برنامه‌ی کار پیشنهادی</a></li>
        <li><a href="#seo-16">۱۶. مدیریت چند سایت</a></li>
        <li><a href="#seo-17">۱۷. عیب‌یابی</a></li>
        <li><a href="#seo-18">۱۸. کارهای خودکار سامانه</a></li>
        <li><a href="#seo-19">۱۹. محدودیت‌ها و چک‌لیست پایانی</a></li>
      </ul>

      <div class="callout">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
        <div>
          <span class="label">راهنمای مطالعه</span>
          <p>بخش‌های <b>۱ تا ۵</b> راه‌اندازی‌اند (برای هر سایت یک بار)، بخش‌های <b>۶ تا ۱۴</b> کار روزمره، و بخش‌های <b>۱۵ تا ۱۹</b> برنامه‌ی کار، مدیریت چند سایت، عیب‌یابی و محدودیت‌ها. برای آموزش به دیگران، بخش ۱۵ را اول به آن‌ها نشان دهید و بعد سراغ جزئیات بروید.</p>
        </div>
      </div>

      <h3 id="seo-0">۰. تصویر کلی و واژه‌ها</h3>
      <p>
        مرکز سئو هر سایت را یک «پروژه» می‌داند و برای هر پروژه یک چرخه‌ی شش‌مرحله‌ای را تکرار می‌کند. هر بخش این فصل
        یکی از این مرحله‌هاست:
      </p>
      <div class="tbl"><table class="ref">
        <thead><tr><th>مرحله</th><th>چه می‌شود</th><th>کجا انجام می‌شود</th></tr></thead>
        <tbody>
          <tr><td>۱. داده</td><td>رفتار واقعی کاربران در گوگل (Search Console)، محتوای واقعی سایت (پلاگین وردپرس) و نتیجه‌ی بازدید (Google Analytics) به سامانه می‌رسد.</td><td>بخش‌های ۳، ۴، ۵</td></tr>
          <tr><td>۲. تحلیل</td><td>کلمات کلیدی دسته‌بندی می‌شوند، فرصت‌ها و مشکل‌ها پیدا می‌شوند، سایت فنی بررسی می‌شود.</td><td>بخش‌های ۶، ۷، ۹، ۱۲</td></tr>
          <tr><td>۳. تصمیم</td><td>سامانه می‌گوید «الان چه کنیم»: چه بنویسیم، کدام مقاله را بازنویسی کنیم، چه چیزی خراب است.</td><td>بخش‌های ۷، ۸، ۱۰</td></tr>
          <tr><td>۴. اقدام</td><td>کار به تقویم محتوا می‌رود، نوشته می‌شود و در وردپرس منتشر می‌شود.</td><td>بخش ۱۱</td></tr>
          <tr><td>۵. اندازه‌گیری</td><td>بعد از انتشار، کلیک و رتبه‌ی همان صفحه و نتیجه‌ی هر فرصت (پس از ۲۸ روز) سنجیده می‌شود.</td><td>بخش‌های ۷، ۱۱، ۱۴</td></tr>
          <tr><td>۶. تکرار</td><td>نتیجه‌ی اندازه‌گیری فرصت‌های تازه می‌سازد و چرخه دوباره می‌چرخد.</td><td>بخش ۱۵</td></tr>
        </tbody>
      </table></div>

      <p><b>واژه‌های مهم</b> (اگر هر کدام برایتان تازه است، قبل از ادامه یک بار بخوانید):</p>
      <div class="tbl"><table class="ref">
        <thead><tr><th>واژه</th><th>معنی ساده</th></tr></thead>
        <tbody>
          <tr><td>ایمپرشن (Impression)</td><td>تعداد دفعاتی که گوگل یکی از صفحه‌های سایت شما را در نتیجه‌ی جست‌وجو به کسی نشان داده، چه کلیک کرده باشد چه نه.</td></tr>
          <tr><td>کلیک (Click)</td><td>تعداد دفعاتی که کسی از نتیجه‌ی گوگل وارد سایت شما شده است.</td></tr>
          <tr><td>CTR</td><td>نرخ کلیک = کلیک ÷ ایمپرشن. اگر یک صفحه در رتبه‌ی خوبی است ولی CTR پایین دارد، معمولاً عنوان یا توضیح آن جذاب نیست.</td></tr>
          <tr><td>رتبه (Position)</td><td>میانگین جایگاه صفحه‌ی شما در نتایج گوگل. ۱ تا ۱۰ یعنی صفحه‌ی اول، ۱۱ تا ۲۰ صفحه‌ی دوم.</td></tr>
          <tr><td>Intent (قصد جست‌وجو)</td><td>کاربر چه می‌خواهد: اطلاعات، مقایسه، خرید، رسیدن به یک برند یا یک جای محلی.</td></tr>
          <tr><td>ایندکس (Index)</td><td>اینکه گوگل صفحه را دیده و در فهرست خودش نگه داشته است. صفحه‌ی ایندکس‌نشده هرگز در جست‌وجو نمی‌آید.</td></tr>
          <tr><td>کانیبالیزیشن</td><td>وقتی دو یا چند صفحه از سایت خودتان برای یک عبارت با هم رقابت می‌کنند و گوگل نمی‌داند کدام را نشان بدهد؛ نتیجه‌اش معمولاً رتبه‌ی ضعیف هر دو است.</td></tr>
          <tr><td>پیلار و کلاستر</td><td>یک موضوع اصلی (پیلار) و مقاله‌های ریز دور آن (کلاستر). سایت منظم یعنی هر موضوع اصلی چند مقاله‌ی پشتیبان دارد که به هم لینک می‌دهند.</td></tr>
          <tr><td>Sitemap</td><td>فایلی (معمولاً sitemap.xml) که فهرست صفحه‌های سایت را به گوگل می‌دهد.</td></tr>
          <tr><td>صفحه‌ی یتیم</td><td>صفحه‌ای که هیچ صفحه‌ی دیگری از سایت به آن لینک نداده؛ گوگل و کاربر سخت پیدایش می‌کنند.</td></tr>
          <tr><td>کلمه‌ی کانونی (Focus keyword)</td><td>عبارتی که نویسنده در Yoast یا Rank Math برای آن صفحه تعیین کرده است.</td></tr>
          <tr><td>تبدیل (Key event)</td><td>کاری که در Google Analytics مهم تعریف شده: رزرو، خرید، ثبت فرم، تماس و مانند آن.</td></tr>
        </tbody>
      </table></div>

      <h3 id="seo-1">۱. پیش‌نیازهای یک‌بارِ کل سامانه</h3>
      <p>
        این بخش را فقط یک بار برای کل سامانه (نه برای هر سایت) انجام می‌دهید و معمولاً مدیر فنی انجامش می‌دهد. بدون آن، دکمه‌ی
        اتصال گوگل کار نمی‌کند.
      </p>
      <ol class="steps">
        <li><b>۱.</b> در <span class="mono">Google Cloud Console</span> یک پروژه بسازید (یا از پروژه‌ی موجود استفاده کنید).</li>
        <li><b>۲.</b> در بخش APIs این چهار سرویس را «Enable» کنید: <span class="mono">Google Search Console API</span>، <span class="mono">PageSpeed Insights API</span>، <span class="mono">Google Analytics Data API</span> و <span class="mono">Google Analytics Admin API</span>. (دو مورد آخر فقط برای بخش ۵ لازم‌اند.)</li>
        <li><b>۳.</b> در «OAuth consent screen» برنامه را تعریف کنید و دو دسترسی فقط‌خواندنی را اضافه کنید: <span class="mono">webmasters.readonly</span> (Search Console) و <span class="mono">analytics.readonly</span> (Analytics). اگر برنامه در حالت «Testing» است، ایمیل‌هایی که قرار است گوگلشان را وصل کنند باید به‌عنوان «Test users» ثبت باشند.</li>
        <li><b>۴.</b> در «Credentials» یک <span class="mono">OAuth client ID</span> از نوع <span class="mono">Web application</span> بسازید.</li>
        <li><b>۵.</b> در سامانه به <b>مرکز سئو ← تنظیمات اتصال گوگل</b> بروید. «آدرس بازگشت (Redirect URI)» را <b>دقیقاً همان‌طور که نمایش داده شده</b> کپی کنید و در Google Cloud زیر «Authorized redirect URIs» بگذارید؛ حتی یک کاراکتر تفاوت اتصال را خراب می‌کند.</li>
        <li><b>۶.</b> <span class="mono">Client ID</span> و <span class="mono">Client Secret</span> را در همان صفحه‌ی «تنظیمات اتصال گوگل» وارد و ذخیره کنید.</li>
        <li><b>۷.</b> به <b>مرکز سئو ← تنظیمات ترکر کلمه کلیدی</b> بروید و فقط فیلد <span class="mono">API Key</span> را پر کنید (کلید همان پروژه‌ی گوگل با فعال بودن PageSpeed Insights). فیلد <span class="mono">Search Engine ID (cx)</span> فقط برای اینکه تنظیمات «کامل» حساب شود پر می‌شود و هر مقداری کافی است؛ خود ترکر رتبه دیگر کار نمی‌کند (بخش ۱۹).</li>
        <li><b>۸.</b> دسترسی کاربران را بدهید: منوهای مرکز سئو برای هر نقش جداست و کاربری که نقش مناسب ندارد منو را نمی‌بیند (فصل ۱۱ همین راهنما). مدیر کل همه‌چیز را می‌بیند.</li>
      </ol>
      <div class="callout">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round"><path d="M12 3v3M12 18v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M3 12h3M18 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/><circle cx="12" cy="12" r="4"/></svg>
        <div>
          <span class="label">دو قاعده‌ی طلایی</span>
          <p>۱) حسابِ گوگلی که بعداً وصل می‌کنید باید در Search Console همان سایت «مالک» یا کاربر دارای دسترسی باشد؛ وگرنه سایت در فهرست ملک‌ها نمی‌آید. ۲) سامانه روی کرون سرور کار می‌کند: کارها از صف اجرا می‌شوند، پس بعد از زدن هر دکمه‌ی «الان» تا حدود یک دقیقه صبر کنید و ناامید نشوید.</p>
        </div>
      </div>

      <h3 id="seo-2">۲. تعریف پروژه</h3>
      <p>
        <b>چرا:</b> هر سایت (یا زیردامنه‌ی جداگانه) یک پروژه است. اطلاعاتی که اینجا می‌نویسید فقط ظاهر نیست؛ هوش مصنوعی و
        محاسبه‌ی «ارزش تجاری» کلمه‌ها مستقیم از آن‌ها استفاده می‌کنند.
      </p>
      <ol class="steps">
        <li><b>۱.</b> <b>مرکز سئو ← پروژه‌های سئو ← ایجاد</b> را بزنید.</li>
        <li><b>۲.</b> فیلدها را طبق جدول زیر پر کنید و ذخیره کنید.</li>
        <li><b>۳.</b> پس از ذخیره، همان پروژه را باز کنید؛ حالا در بالای صفحه دکمه‌های اتصال (بخش‌های بعد) را می‌بینید.</li>
      </ol>
      <div class="tbl"><table class="ref">
        <thead><tr><th>فیلد</th><th>چه بنویسیم</th><th>اثرش</th></tr></thead>
        <tbody>
          <tr><td>نام پروژه</td><td>نام قابل تشخیص، مثلاً «گنجنامه - سایت اصلی». اگر چند زیردامنه دارید برای هرکدام یک پروژه با نام جدا بسازید.</td><td>نام در همه‌ی فهرست‌ها و هشدارها دیده می‌شود.</td></tr>
          <tr><td>آدرس وب‌سایت</td><td>آدرس کامل سایت، با https.</td><td>پایش آنلاین‌بودن و SSL، ممیزی فنی و تشخیص صفحه‌ی اصلی.</td></tr>
          <tr><td>آدرس Sitemap</td><td>آدرس کامل فایل <span class="mono">sitemap.xml</span> یا فهرست sitemap.</td><td>«تحلیل‌گر Sitemap» و تشخیص صفحه‌های تازه از روی آن کار می‌کند.</td></tr>
          <tr><td>هدف اصلی کسب‌وکار</td><td>یکی از: فروش، جذب سرنخ، رزرو، برندینگ، انتشار محتوا، افیلیت، جذب تماس، جذب فرم.</td><td>ارزش تجاری کلمه‌های کلیدی را تعیین می‌کند (مثلاً برای «رزرو»، عبارت‌های رزرو ارزشمندترند).</td></tr>
          <tr><td>بازار هدف</td><td>«ایران» یا نام استان/شهر برای کسب‌وکار محلی.</td><td>به هوش مصنوعی می‌گوید پیشنهادها برای کجا باشد.</td></tr>
          <tr><td>نام برند</td><td>نام برند به همان شکل که مردم تایپ می‌کنند.</td><td>کمک به تشخیص جست‌وجوی برندی.</td></tr>
          <tr><td>فعال</td><td>روشن برای پروژه‌ی در حال کار.</td><td>پروژه‌ی غیرفعال از پایش آنلاین‌بودن و همگام‌سازی شبانه‌ی Analytics کنار می‌رود.</td></tr>
          <tr><td>مخاطب هدف</td><td>یک جمله: چه کسانی مشتری شما هستند.</td><td>ورودی هوش مصنوعی.</td></tr>
          <tr><td><b>خدمات و محصولات واقعی</b></td><td><b>مهم‌ترین فیلد.</b> دقیق بنویسید چه دارید و چه ندارید. مثال: «داریم: تله‌کابین، اجاره ویلا، تور غار علیصدر — نداریم: پیست اسکی، فروش سوغات».</td><td>هوش مصنوعی هنگام پیشنهاد کلمه‌ی تازه فقط بر همین اساس قضاوت می‌کند. اگر خالی بماند پیشنهادها با کسب‌وکار شما نمی‌خوانند و سامانه هشدار می‌دهد.</td></tr>
          <tr><td>کلمات برند/حذف‌شونده از سینک</td><td>هر عبارت در یک سطر (یا با کاما). مثلاً نام برند خودتان.</td><td>هر کلمه‌ی کلیدی که یکی از این عبارت‌ها را داشته باشد در سینک بعدی کنار گذاشته می‌شود تا فهرست کلمه‌ها با جست‌وجوی برندی شلوغ نشود. روی آمار کلی روزانه، کشور و دستگاه اثر ندارد.</td></tr>
          <tr><td>یادداشت‌ها</td><td>هر چیزی که تیم باید بداند.</td><td>فقط برای انسان.</td></tr>
          <tr><td>رقبا</td><td>برای هر رقیب: دامنه، آدرس Sitemap (اختیاری، فقط اگر غیرمعمول است) و یادداشت.</td><td>«تحلیل شکاف رقبا» از این‌ها استفاده می‌کند (بخش ۸).</td></tr>
        </tbody>
      </table></div>
      <div class="callout">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
        <div>
          <span class="label">اشتباه رایج</span>
          <p>نوشتن «خدمات واقعی» به‌صورت کلی («گردشگری»). هر چه فهرست دقیق‌تر و شامل «نداریم» باشد، پیشنهاد کلمه‌ی کلیدی بی‌ربط کمتر می‌شود.</p>
        </div>
      </div>

      <h3 id="seo-3">۳. اتصال Search Console و دریافت داده</h3>
      <p>
        <b>چرا:</b> Search Console تنها منبع «رفتار واقعی کاربران در گوگل» است. بدون این اتصال کلمات کلیدی، رتبه‌ها و بیشتر
        فرصت‌ها ساخته نمی‌شوند.
      </p>
      <ol class="steps">
        <li><b>۱.</b> پروژه را باز کنید (<b>پروژه‌های سئو ← ویرایش</b>) و دکمه‌ی <b>«اتصال Google Search Console»</b> را بزنید.</li>
        <li><b>۲.</b> وارد حساب گوگلی شوید که به سایت دسترسی دارد. صفحه‌ی گوگل دو اجازه می‌خواهد: Search Console و Analytics. <b>هر دو را تیک بزنید</b> (اگر Analytics را نمی‌خواهید، فقط اولی لازم است ولی بعداً باید دوباره وصل کنید).</li>
        <li><b>۳.</b> به سامانه برمی‌گردید. در بخش «اتصال Google Search Console» همان صفحه، «وضعیت اتصال» باید <b>«متصل»</b> باشد.</li>
        <li><b>۴.</b> دکمه‌ی <b>«انتخاب ملک Search Console»</b> را بزنید و ملک این سایت را انتخاب کنید. ملک ممکن است به شکل «دامنه» یا «آدرس با پیشوند» باشد؛ همان را بردارید که در Search Console خودتان تعریف شده است.</li>
        <li><b>۵.</b> <b>«همگام‌سازی الان»</b> را بزنید. سامانه تا حدود یک سال تاریخچه را (تا سه روز پیش، چون گوگل داده‌ی سه روز آخر را هنوز نهایی نکرده) می‌آورد. بار اول چند دقیقه طول می‌کشد.</li>
        <li><b>۶.</b> بعد از پایان همگام‌سازی، تحلیل خودکار اجرا می‌شود (کلمات کلیدی ساخته و فرصت‌ها پیدا می‌شوند). فقط اگر چیزی را عوض کردید و می‌خواهید دوباره تحلیل شود، <b>«تحلیل الان»</b> را بزنید؛ این دکمه با گوگل تماس نمی‌گیرد.</li>
        <li><b>۷.</b> اگر آدرس Sitemap را داده‌اید، <b>«همگام‌سازی Sitemap الان»</b> را هم بزنید.</li>
        <li><b>۸.</b> اختیاری ولی توصیه‌شده: <b>«طبقه‌بندی هوشمند کلمات»</b>. هوش مصنوعی برای هر کلمه Intent، ارزش تجاری و خوشه‌ی موضوعی تعیین می‌کند (ستون‌های خالی فهرست کلمات پر می‌شوند).</li>
      </ol>
      <p><b>وضعیت اتصال</b> را این‌طور بخوانید:</p>
      <div class="tbl"><table class="ref">
        <thead><tr><th>وضعیت</th><th>یعنی</th><th>کار شما</th></tr></thead>
        <tbody>
          <tr><td>متصل نشده</td><td>هنوز حساب گوگل وصل نشده.</td><td>«اتصال Google Search Console».</td></tr>
          <tr><td>متصل</td><td>همه‌چیز درست است.</td><td>ملک را انتخاب کنید و همگام‌سازی کنید.</td></tr>
          <tr><td>نیاز به اتصال مجدد</td><td>نشست گوگل منقضی شده یا دسترسی پس گرفته شده.</td><td>دوباره «اتصال Google Search Console» (یک کلیک و تأیید).</td></tr>
          <tr><td>خطا</td><td>همگام‌سازی شکست خورده؛ متن خطا در «آخرین خطا» همان بخش نوشته می‌شود. (اگر هشدارها در «تنظیمات هشدار سئو» روشن باشند، فقط بار اول خبر می‌دهد.)</td><td>خطا را بخوانید؛ معمولاً دسترسی حساب یا غیرفعال بودن API است (بخش ۱۷).</td></tr>
        </tbody>
      </table></div>
      <p>همگام‌سازی خودکار هر شب ساعت ۰۱:۰۰ (به وقت تهران) انجام می‌شود؛ بعد از اتصال اولیه دیگر لازم نیست دستی بزنید.</p>

      <h3 id="seo-4">۴. اتصال وردپرس (پلاگین)</h3>
      <p>
        <b>چرا:</b> Search Console می‌گوید «کاربران چه چیزی جست‌وجو می‌کنند»؛ پلاگین می‌گوید «سایت شما چه دارد». وقتی
        هر دو کنار هم باشند سامانه می‌داند چه موضوعی را هنوز ننوشته‌اید، کدام مقاله باید بازنویسی شود و کدام صفحه یتیم
        است. پلاگین <b>فقط می‌خواند</b>؛ هیچ چیزی در سایت شما تغییر نمی‌دهد و <b>متن کامل نوشته‌ها را هرگز نمی‌فرستد</b>.
      </p>

      <p><b>۴-۱. چه چیزی فرستاده می‌شود؟</b> مدیر سایت در تنظیمات خودِ پلاگین می‌گوید کدام نوع اطلاعات خارج شود. سامانه هم
        سمت خودش بخشی را که اجازه نداشته نگه نمی‌دارد.</p>
      <div class="tbl"><table class="ref">
        <thead><tr><th>مجوز در پلاگین</th><th>چه چیزی می‌رود</th><th>پیش‌فرض</th></tr></thead>
        <tbody>
          <tr><td>فهرست محتوا (الزامی)</td><td>عنوان، آدرس، وضعیت، تاریخ‌ها، تعداد کلمات.</td><td>روشن، غیرقابل خاموش</td></tr>
          <tr><td>متای سئو</td><td>کلمه‌ی کانونی، عنوان و توضیح سئو، canonical و noindex از Yoast یا Rank Math.</td><td>روشن</td></tr>
          <tr><td>تیترها و لینک‌ها</td><td>سرتیترها و لینک‌های داخلی و خارجی داخل متن.</td><td>روشن</td></tr>
          <tr><td>تصاویر</td><td>تعداد تصویر و تعداد تصاویر بدون alt.</td><td>روشن</td></tr>
          <tr><td>دسته‌ها و برچسب‌ها</td><td>نام دسته و برچسب هر نوشته.</td><td>روشن</td></tr>
          <tr><td>نام نویسنده</td><td>نام نمایشی نویسنده.</td><td>خاموش</td></tr>
          <tr><td>ووکامرس</td><td>قیمت، موجودی و امتیاز محصول (فقط اگر ووکامرس نصب باشد).</td><td>خاموش</td></tr>
          <tr><td>سلامت سایت</td><td>مسدود بودن موتورهای جست‌وجو، نیاز به به‌روزرسانی وردپرس و افزونه‌ها (فقط نام و نسخه) و آدرس‌هایی که «۴۰۴» داده‌اند.</td><td>روشن</td></tr>
          <tr><td>عبارت‌های جست‌وجوی داخل سایت</td><td>آنچه بازدیدکننده‌ها در کادر جست‌وجوی خودِ سایت تایپ کرده‌اند (بدون هیچ مشخصات بازدیدکننده؛ عبارت‌های شبیه ایمیل یا شماره حذف می‌شوند).</td><td>خاموش</td></tr>
        </tbody>
      </table></div>

      <p><b>۴-۲. نصب پلاگین</b></p>
      <ol class="steps">
        <li><b>۱.</b> در سامانه به <b>مرکز سئو ← پروژه‌های سئو</b> بروید و دکمه‌ی <b>«دانلود پلاگین وردپرس»</b> را بزنید تا فایل zip دانلود شود.</li>
        <li><b>۲.</b> در پیشخوان وردپرس سایت: <b>افزونه‌ها ← افزودن ← بارگذاری افزونه</b>، فایل را انتخاب و <b>نصب</b> و بعد <b>فعال‌سازی</b> کنید.</li>
        <li><b>۳.</b> نیازمندی: وردپرس ۵٫۳ به بالا و PHP ۷٫۴ به بالا.</li>
      </ol>

      <p><b>۴-۳. اتصال پلاگین به پروژه</b> (با یک «کد اتصال» یک‌بارمصرف)</p>
      <ol class="steps">
        <li><b>۱.</b> در سامانه همان پروژه را باز کنید و <b>«اتصال وردپرس»</b> را بزنید. یک کد به شکل <span class="mono">XXXX-XXXX-XXXX</span> می‌دهد که <b>۲۴ ساعت اعتبار دارد و فقط یک بار</b> کار می‌کند. آن را کپی کنید.</li>
        <li><b>۲.</b> در وردپرس به <b>تنظیمات ← اتصال CRM سئو</b> بروید.</li>
        <li><b>۳.</b> «آدرس CRM» را (مثلاً <span class="mono">https://crm.example.com</span>) و «کد اتصال» را وارد و <b>«اتصال»</b> را بزنید.</li>
        <li><b>۴.</b> صفحه می‌گوید «وضعیت: متصل». در سامانه، فهرست پروژه‌ها در ستون «وردپرس» وضعیت اتصال را نشان می‌دهد.</li>
      </ol>
      <p>اگر کد منقضی یا اشتباه باشد، اتصال رد می‌شود؛ کافی است دوباره «اتصال وردپرس» را بزنید و کد تازه بگیرید. بعد از اتصال، دکمه‌های پلاگین عبارت‌اند از <b>«همگام‌سازی همین حالا»</b>، <b>«ارسال دوباره‌ی همه از ابتدا»</b> و <b>«قطع اتصال»</b>.</p>

      <p><b>۴-۴. تنظیمات پلاگین (برای مدیر سایت)</b> در همان صفحه‌ی «اتصال CRM سئو»:</p>
      <div class="tbl"><table class="ref">
        <thead><tr><th>بخش</th><th>چه می‌کند</th></tr></thead>
        <tbody>
          <tr><td>نوع اطلاعات</td><td>همان مجوزهای جدول ۴-۱؛ هر کدام را می‌توان خاموش کرد.</td></tr>
          <tr><td>نوع محتوا</td><td>کدام انواع نوشته (نوشته، برگه، محصول، نوع سفارشی) فرستاده شود. پیش‌فرض: نوشته و برگه.</td></tr>
          <tr><td>پیش‌نویس‌ها</td><td>اگر تیک بخورد، پیش‌نویس، «در انتظار بررسی»، زمان‌بندی‌شده و خصوصی هم می‌روند، ولی <b>فقط عنوان و آدرس</b>، تا سامانه موضوع تکراری پیشنهاد ندهد.</td></tr>
          <tr><td>استثناها</td><td>شناسه‌ی نوشته‌هایی که هرگز نروند، و دسته‌هایی که باید کنار بمانند.</td></tr>
          <tr><td>زمان و فشار</td><td>بازه‌ی ساعتی مجاز برای همگام‌سازی خودکار (به وقت سایت) و حداکثر تعداد دسته در هر نوبت (هر دسته ۵۰ مورد). برای هاست ضعیف عدد را کم کنید.</td></tr>
          <tr><td>به‌روزرسانی</td><td>«پلاگین خودکار از CRM به‌روز شود»: نسخه‌ی تازه را خودِ سامانه می‌دهد؛ لازم نیست zip را روی هر سایت دوباره نصب کنید.</td></tr>
          <tr><td>شفافیت</td><td>جدول «آخرین ارتباط‌ها با CRM» و پیش‌نمایش آنچه فرستاده می‌شود؛ همه‌چیز قابل بازرسی است.</td></tr>
        </tbody>
      </table></div>

      <p><b>۴-۵. ارسال چطور کار می‌کند؟</b> پلاگین هر ۱۰ دقیقه و حدود ۳۰ ثانیه پس از ذخیره‌ی هر نوشته، تغییرها را در دسته‌های ۵۰تایی می‌فرستد. اگر یک دسته شکست بخورد، از همان‌جا ادامه می‌دهد و چیزی گم نمی‌شود. سایت کم‌بازدید که وردپرسش کرون نمی‌زند با ورود مدیر به پیشخوان عقب‌افتادگی را جبران می‌کند. اگر ارسال خطا بدهد، متن خطای CRM در صفحه‌ی پلاگین و در صفحه‌ی «محتوای سایت (وردپرس)» دیده می‌شود.</p>
      <p><b>۴-۶. قطع اتصال:</b> از صفحه‌ی ویرایش پروژه <b>«قطع اتصال وردپرس»</b> یا از خود پلاگین <b>«قطع اتصال»</b>. اطلاعات قبلی می‌ماند ولی چیز تازه‌ای نمی‌آید؛ برای وصل دوباره کد تازه لازم است.</p>
      <div class="callout">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
        <div>
          <span class="label">نکته‌های فنی پلاگین</span>
          <p>کلمه‌ی کانونی از Yoast و Rank Math خوانده می‌شود. اگر صفحه با Elementor ساخته شده، متن و سرتیترها از داده‌ی خودِ Elementor بازسازی می‌شوند؛ سازنده‌های دیگر (Divi و مانند آن) فعلاً تجزیه نمی‌شوند. نسخه‌ی فعلی پلاگین ۱٫۱٫۰ است.</p>
        </div>
      </div>

      <h3 id="seo-5">۵. اتصال Google Analytics (اختیاری)</h3>
      <p>
        <b>چرا:</b> Search Console می‌گوید چند نفر از گوگل آمدند؛ Analytics می‌گوید بعد از ورود چه کردند: چند نشست، چند تبدیل
        (رزرو، خرید، فرم) و چند نفر از دستیارهای هوش مصنوعی مثل ChatGPT آمدند.
      </p>
      <ol class="steps">
        <li><b>۱.</b> پیش‌نیاز: بخش ۱ (دو API فعال و دسترسی Analytics در صفحه‌ی رضایت) انجام شده باشد، و حساب گوگلِ متصل به این پروژه به ویژگی GA4 سایت دسترسی داشته باشد.</li>
        <li><b>۲.</b> در ویرایش پروژه اگر دکمه‌ی <b>«اتصال مجدد برای Google Analytics»</b> دیدید، آن را بزنید و این بار گزینه‌ی Analytics را تیک بزنید (پروژه‌هایی که قبل از این قابلیت وصل شده‌اند اجازه‌ی Analytics ندارند).</li>
        <li><b>۳.</b> <b>«انتخاب ویژگی Google Analytics (GA4)»</b> را بزنید و ویژگی سایت را انتخاب کنید. دریافت آمار بلافاصله در صف می‌رود.</li>
        <li><b>۴.</b> هر شب ساعت ۰۲:۰۰ آمار ۲۸ روز اخیر تازه می‌شود؛ هر وقت خواستید <b>«همگام‌سازی Analytics الان»</b> را بزنید. اگر خطایی باشد، متنش در همان صفحه و در برد وضعیت (بخش ۹) نوشته می‌شود.</li>
      </ol>
      <p><b>چه چیزی به شما می‌دهد؟</b></p>
      <ul>
        <li>در «محتوای سایت (وردپرس)» دو ستون «نشست (GA، ۲۸ روز)» و «تبدیل (GA)» برای هر صفحه.</li>
        <li>نشان <b>«ترافیک بدون تبدیل»</b> برای صفحه‌ای که حداقل ۱۰۰ نشست داشته ولی هیچ تبدیلی نه (فقط برای سایتی که اصلاً تبدیل ثبت می‌کند، وگرنه همه‌ی صفحه‌ها بی‌دلیل علامت می‌خوردند).</li>
        <li>در «جزئیات» هر سایت در برد وضعیت: جمع نشست‌ها و تبدیل‌ها و تعداد ورودی از دستیارهای هوش مصنوعی.</li>
      </ul>

      <h3 id="seo-6">۶. خواندن داده‌ها: داشبورد، کلمات کلیدی و گزارش‌های تحلیلی</h3>
      <p>بعد از اتصال و همگام‌سازی، این صفحه‌ها پر می‌شوند. همه در گروه منوی <b>مرکز سئو</b> هستند.</p>

      <p><b>داشبورد سئو</b> — نمای یک‌نگاهه برای مدیر: فرصت‌های باز، فرصت‌های برطرف‌شده در این ماه، نرخ موفقیت اقدام‌ها، آمار کلمات کلیدی، محتوای نزدیک به مهلت و پیشنهادهای کلمه‌ی کلیدی در انتظار تأیید. روزانه اول اینجا را ببینید.</p>

      <p><b>کلمات کلیدی</b> — فهرست عبارت‌هایی که گوگل سایت شما را برایشان نشان داده، همه از داده‌ی واقعی. «حال» یعنی ۲۸ روز اخیر و «قبل» یعنی ۲۸ روز پیش از آن:</p>
      <div class="tbl"><table class="ref">
        <thead><tr><th>ستون</th><th>معنی</th></tr></thead>
        <tbody>
          <tr><td>کلمه کلیدی</td><td>عبارتی که کاربر تایپ کرده است.</td></tr>
          <tr><td>رتبه فعلی</td><td>میانگین جایگاه در ۲۸ روز اخیر.</td></tr>
          <tr><td>روند</td><td>«در حال رشد» یا «در حال افت» (اگر رتبه بیش از یک پله جابه‌جا شده باشد)، «ثابت»، یا «تازه‌ظاهرشده» (در دوره‌ی قبل نبود).</td></tr>
          <tr><td>Intent</td><td>اطلاعاتی، مقایسه‌ای/تجاری، تراکنشی/خرید، مسیریابی به برند، محلی (بعد از «طبقه‌بندی هوشمند کلمات» پر می‌شود).</td></tr>
          <tr><td>ارزش تجاری</td><td>بالا، متوسط، پایین؛ بر اساس هدف پروژه.</td></tr>
          <tr><td>خوشه موضوعی</td><td>موضوع اصلی که این کلمه به آن تعلق دارد.</td></tr>
          <tr><td>کلیک / ایمپرشن / CTR</td><td>۲۸ روز اخیر.</td></tr>
          <tr><td>کانیبالیزیشن</td><td>بدون ریسک، ریسک احتمالی، ریسک بالا (وقتی چند صفحه‌ی خودی برای همین کلمه رقابت می‌کنند).</td></tr>
          <tr><td>صفحه فرود</td><td>صفحه‌ای که گوگل بیشتر برای این کلمه نشان می‌دهد.</td></tr>
          <tr><td>امتیاز فرصت</td><td>میزان فرصتِ بهبود را از روی رتبه و ایمپرشن می‌سنجد؛ بالاتر یعنی ارزش وقت گذاشتن بیشتر.</td></tr>
        </tbody>
      </table></div>
      <p>فیلترها: پروژه، روند، Intent و کانیبالیزیشن. دکمه‌ی «بررسی ایندکس» وضعیت ایندکس صفحه‌ی فرود را از خود گوگل می‌پرسد.</p>

      <p><b>ترکیب ترافیک</b> — ترافیک را بر حسب کشور بازدیدکننده‌ها، نحوه‌ی نمایش در گوگل (Search appearance) و نوع جست‌وجو (وب، تصویر، ویدئو، اخبار، Discover و …) نشان می‌دهد. برای سؤال‌هایی مثل «آیا بخشی از ترافیک ما از جست‌وجوی تصویر می‌آید؟».</p>
      <p><b>تحلیل‌گر Sitemap</b> — سلامت فایل sitemap (خطا و هشدارهایی که خود گوگل هنگام خواندن پیدا کرده)، رشد تعداد صفحه‌ها در طول زمان و جدولی از هر صفحه با نوع محتوا، تاریخ آخرین تغییر (lastmod) و کلیک، ایمپرشن و رتبه‌ی همان صفحه از Search Console.</p>
      <p><b>نقشه موضوعی (پیلار-کلاستر)</b> — نمای درختی خوشه‌های موضوعی یک پروژه با کلمه‌های واقعی، پیشنهادها و آیتم‌های تقویم هر خوشه، و هشدار هم‌پوشانی. با آن می‌بینید کدام موضوع محتوای تکراری دارد و کدام هنوز خالی است.</p>

      <div class="howto">
        <div class="howto-label">نمای چند صفحه‌ی اصلی</div>
        <div class="shots">
          <figure class="shot">
            <img src="/system-guide-assets/shots/36-seo-dashboard.png" alt="داشبورد سئو">
            <figcaption><span class="step-n">۱</span> داشبورد سئو: نرخ موفقیت اقدامات، فرصت‌های باز، محتوای نزدیک به مهلت و پیشنهادهای در انتظار تأیید.</figcaption>
          </figure>
          <figure class="shot">
            <img src="/system-guide-assets/shots/37-seo-projects-list.png" alt="فهرست پروژه‌های سئو">
            <figcaption><span class="step-n">۲</span> فهرست پروژه‌ها: هر سایت یک پروژه‌ی مستقل با هدف، وضعیت اتصال Search Console و فعال/غیرفعال بودن.</figcaption>
          </figure>
          <figure class="shot">
            <img src="/system-guide-assets/shots/38-seo-keyword-profiles.png" alt="کلمات کلیدی">
            <figcaption><span class="step-n">۳</span> کلمات کلیدی: رتبه، روند، کلیک و ایمپرشن واقعی هر کلمه با فیلتر روند، Intent و کانیبالیزیشن.</figcaption>
          </figure>
          <figure class="shot">
            <img src="/system-guide-assets/shots/44-seo-keyword-suggestions.png" alt="پیشنهادهای کلمات کلیدی هوش مصنوعی">
            <figcaption><span class="step-n">۴</span> پیشنهادهای کلمات کلیدی: نتیجه‌ی کشف با هوش مصنوعی، آماده‌ی تأیید یا رد.</figcaption>
          </figure>
          <figure class="shot">
            <img src="/system-guide-assets/shots/42-seo-pillar-cluster-map.png" alt="نقشه موضوعی پیلار-کلاستر">
            <figcaption><span class="step-n">۵</span> نقشه موضوعی: خوشه‌های محتوایی پروژه و کلمات هر خوشه.</figcaption>
          </figure>
          <figure class="shot">
            <img src="/system-guide-assets/shots/41-seo-content-calendar.png" alt="تقویم محتوا">
            <figcaption><span class="step-n">۶</span> تقویم محتوا: پیگیری هر آیتم از ایده تا انتشار، با مسئول و مهلت.</figcaption>
          </figure>
          <figure class="shot">
            <img src="/system-guide-assets/shots/40-seo-technical-audits.png" alt="ممیزی فنی سئو">
            <figcaption><span class="step-n">۷</span> ممیزی فنی: امتیاز کلی و امتیاز سرعت موبایل هر اسکن با تعداد مشکلات.</figcaption>
          </figure>
        </div>
      </div>

      <h3 id="seo-7">۷. فرصت‌ها و صف اقدام</h3>
      <p>
        <b>چرا:</b> هیچ‌کس وقت ندارد هزار کلمه را یکی‌یکی نگاه کند. سامانه هر شب داده را می‌خواند، چیزهای قابل اقدام را پیدا
        می‌کند و با شواهد جلوی شما می‌گذارد: «این صفحه رتبه‌ی خوب دارد ولی کسی کلیک نمی‌کند»، «ترافیک این موضوع افت
        کرده» و مانند آن.
      </p>
      <p><b>فرصت‌های سئو</b> (<b>مرکز سئو ← فرصت‌های سئو</b>) — انواع مهم:</p>
      <div class="tbl"><table class="ref">
        <thead><tr><th>نوع</th><th>چه وقت ساخته می‌شود</th><th>معمولاً چه کنیم</th></tr></thead>
        <tbody>
          <tr><td>فرصت بهبود CTR/رتبه</td><td>کلمه‌ای با حداقل ۱۰۰ ایمپرشن که کلیکش از حد معمول جایگاه خودش بسیار کمتر است.</td><td>عنوان و توضیح متا را جذاب‌تر کنید؛ تطابق با قصد جست‌وجو را ببینید.</td></tr>
          <tr><td>افت ترافیک</td><td>کلیک ۲۸ روز اخیر دست‌کم ۲۰٪ کمتر از دوره‌ی قبل (و مقایسه با همین زمان سال پیش برای تشخیص فصلی‌بودن).</td><td>اول مشکل فنی و ایندکس، بعد صفحه‌های پراُفت.</td></tr>
          <tr><td>در آستانه‌ی صفحه‌ی اول</td><td>کلمه‌ای در رتبه‌ی ۱۱ تا ۲۰ با دست‌کم ۲۰ ایمپرشن.</td><td>همین صفحه را تقویت کنید (محتوا، لینک داخلی)؛ ارزان‌ترین برد است.</td></tr>
          <tr><td>بازگشت از صفحه‌ی اول</td><td>کلمه‌ای که قبلاً صفحه‌ی اول بوده و افتاده.</td><td>ببینید چه چیزی تغییر کرده؛ محتوا را تازه کنید.</td></tr>
          <tr><td>کانیبالیزیشن (و در سطح خوشه)</td><td>چند صفحه‌ی خودی برای یک عبارت (یا یک موضوع) با هم رقابت می‌کنند.</td><td>با «تعیین صفحه‌ی اصلی» تصمیم بگیرید: ادغام، تمایز یا ریدایرکت.</td></tr>
          <tr><td>رشد/افت غیرعادی</td><td>جهش آماری غیرمعمول در کلیک، ایمپرشن، رتبه‌ی میانگین یا CTR کل سایت، یک صفحه یا یک کلمه (به حداقل ۱۴ روز سابقه نیاز دارد).</td><td>علت رشد را بیابید و تکرار کنید؛ علت افت را فوری بررسی کنید.</td></tr>
          <tr><td>صفحه‌ی قدیمی بدون ترافیک</td><td>صفحه‌ای قدیمی‌تر از ۹۰ روز که ترافیک ندارد.</td><td>بازنویسی، ادغام یا ریدایرکت.</td></tr>
          <tr><td>صفحه‌ی تازه بدون نمایش</td><td>صفحه‌ای بین ۲۱ تا ۹۰ روز عمر که هنوز ایمپرشن ندارد.</td><td>ایندکس را بررسی کنید و از صفحه‌های دیگر به آن لینک بدهید.</td></tr>
          <tr><td>صفحه‌ی پرترافیک با لینک داخلی کم</td><td>دست‌کم ۱۰۰ ایمپرشن و حداکثر یک لینک داخلی ورودی.</td><td>از صفحه‌های هم‌موضوع به آن لینک بدهید.</td></tr>
          <tr><td>محتوای ضعیف‌تر از هم‌خوشه‌ها</td><td>محتوای منتشرشده‌ای که کلیکش کمتر از ۳۰٪ میانگین هم‌خوشه‌هایش است (حداقل دو محتوای منتشرشده در خوشه لازم است).</td><td>بازنویسی یا تقویت لینک داخلی.</td></tr>
        </tbody>
      </table></div>
      <p>هر فرصت یک <b>اولویت</b> (بالا، متوسط، پایین) و یک <b>قطعیت</b> (بالا، متوسط، پایین) دارد. قطعیت پایین یعنی سامانه مطمئن نیست؛ با دقت بیشتر بررسی کنید. با «مشاهده شواهد» دقیقاً می‌بینید سامانه چرا چنین نتیجه‌ای گرفته (اعداد و اقدام‌های پیشنهادی به فارسی).</p>

      <p><b>چرخه‌ی وضعیت یک فرصت:</b> «شناسایی‌شده» ← «بررسی‌شده» ← «برطرف‌شده» (یا «رد‌شده» اگر به درد نمی‌خورد). دکمه‌ها:</p>
      <div class="tbl"><table class="ref">
        <thead><tr><th>دکمه</th><th>کاربرد</th></tr></thead>
        <tbody>
          <tr><td>مشاهده شواهد</td><td>اعداد و دلیل شناسایی و اقدام‌های پیشنهادی.</td></tr>
          <tr><td>بررسی شد</td><td>یعنی خواندمش و در حال تصمیم‌ام (فقط برای «شناسایی‌شده»).</td></tr>
          <tr><td>برنامه‌ریزی محتوا</td><td>یک آیتم در تقویم محتوا می‌سازد (عنوان، نوع، مسئول، مهلت).</td></tr>
          <tr><td>تعیین صفحه‌ی اصلی</td><td>فقط برای کانیبالیزیشن: صفحه‌ی برنده و اقدام برای بقیه (ادغام، تمایز و …) را ثبت می‌کنید.</td></tr>
          <tr><td>برطرف شد</td><td>بعد از انجام کار. <b>مهم:</b> همین کلیک مبدأ اندازه‌گیری را ثبت می‌کند.</td></tr>
          <tr><td>رد کردن</td><td>برای فرصتی که به کسب‌وکار شما ربط ندارد.</td></tr>
          <tr><td>نتیجه اقدام</td><td>بعد از ۲۸ روز دیده می‌شود (پایین را بخوانید).</td></tr>
        </tbody>
      </table></div>
      <div class="callout">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
        <div>
          <span class="label">چطور بفهمیم کارمان اثر داشت؟</span>
          <p>وقتی «برطرف شد» را می‌زنید، سامانه کلیک و ایمپرشن و رتبه‌ی همان موضوع را در همان لحظه ثبت می‌کند. ۲۸ روز بعد دوباره می‌سنجد و ستون «نتیجه» یکی از اینها می‌شود: «در انتظار نتیجه»، «بهبود یافت» (کلیک دست‌کم ۱۵٪ بهتر)، «بدون تغییر محسوس» یا «بدتر شد». این‌طوری می‌فهمید کدام نوع اقدام در سایت شما جواب می‌دهد. اگر هیچ‌وقت «برطرف شد» نزنید، هرگز نتیجه‌ای نمی‌بینید.</p>
        </div>
      </div>
      <p><b>صف اقدام</b> (<b>مرکز سئو ← صف اقدام</b>): فرصت‌ها، مشکل‌های ممیزی فنی و پیشنهادهای کلمه‌ی کلیدی در انتظار تأیید را در <b>یک فهرست اولویت‌دار</b> برای پروژه‌ی انتخاب‌شده کنار هم می‌گذارد. اگر وقت ندارید و فقط می‌خواهید بدانید «الان چه کنم»، از همین صفحه از بالا شروع کنید.</p>

      <h3 id="seo-8">۸. کلمه‌ی کلیدی تازه: پیشنهاد هوش مصنوعی و شکاف رقبا</h3>
      <p>
        <b>چرا:</b> کلمه‌های بخش ۶ همه چیزهایی‌اند که گوگل قبلاً برایشان سایت را نشان داده. برای موضوع‌هایی که هنوز
        سراغشان نرفته‌اید، دو راه دارید. <b>هر دو حدس‌اند، نه داده‌ی واقعی</b>، و باید تأیید شوند.
      </p>
      <ol class="steps">
        <li><b>۱.</b> مطمئن شوید فیلد «خدمات و محصولات واقعی» پروژه پر است (بخش ۲).</li>
        <li><b>۲.</b> در ویرایش پروژه <b>«کشف کلمات کلیدی جدید»</b> را بزنید و تعداد پیشنهاد را بدهید (از ۵ تا ۵۰، پیش‌فرض ۲۰). اگر پلاگین وردپرس وصل باشد، هوش مصنوعی فهرست صفحه‌های واقعی سایت را هم می‌بیند و موضوعی را که صفحه دارد دوباره پیشنهاد نمی‌دهد؛ پیشنهادی که دقیقاً کلمه‌ی کانونی یک صفحه‌ی منتشرشده باشد حذف می‌شود.</li>
        <li><b>۳.</b> <b>«تحلیل شکاف رقبا»</b> (اگر رقیب تعریف کرده‌اید): sitemap رقبا خوانده می‌شود و موضوع‌هایی که چند رقیب پوشش داده‌اند ولی شما محتوایی برایشان ندارید به‌عنوان پیشنهاد اضافه می‌شود. هر شنبه ساعت ۰۳:۰۰ صبح خودکار هم اجرا می‌شود.</li>
        <li><b>۴.</b> به <b>مرکز سئو ← پیشنهادهای کلمات کلیدی</b> بروید. ستون‌ها: کلمه‌ی پیشنهادی، Intent، خوشه‌ی موضوعی، نوع محتوای پیشنهادی، «دلیل» و وضعیت (جدید، تأییدشده، رد‌شده).</li>
        <li><b>۵.</b> برای هر ردیف تصمیم بگیرید: <b>«تأیید»</b> (قابل قبول)، <b>«رد کردن»</b> (بی‌ربط) یا <b>«ایجاد آیتم محتوا»</b> (عنوان، مسئول، مهلت را می‌دهید و مستقیم وارد تقویم محتوا می‌شود). <b>«خروجی اکسل»</b> فهرست را برای بحث تیمی می‌دهد.</li>
      </ol>
      <p>در «نقشه موضوعی» می‌بینید این پیشنهادها در کدام خوشه می‌نشینند و کجا پوشش خالی است.</p>

      <h3 id="seo-9">۹. محتوای سایت (وردپرس) و برد وضعیت سایت‌ها</h3>
      <p><b>محتوای سایت (وردپرس)</b> — <b>مرکز سئو ← محتوای سایت (وردپرس)</b>: فهرست هر صفحه و نوشته‌ی سایت‌های وصل‌شده، کنار داده‌ی گوگل و (اگر وصل باشد) Analytics.</p>
      <div class="tbl"><table class="ref">
        <thead><tr><th>ستون</th><th>معنی</th></tr></thead>
        <tbody>
          <tr><td>عنوان، پروژه، نوع، وضعیت</td><td>مشخصات صفحه.</td></tr>
          <tr><td>مشکلات</td><td>برچسب‌های رنگی مشکل‌های پیداشده (جدول زیر).</td></tr>
          <tr><td>کلیک، ایمپرشن، رتبه</td><td>۲۸ روز اخیر از Search Console.</td></tr>
          <tr><td>نشست (GA)، تبدیل (GA)</td><td>۲۸ روز اخیر از Analytics (بخش ۵).</td></tr>
          <tr><td>کلمه کانونی</td><td>از Yoast یا Rank Math.</td></tr>
          <tr><td>لینک ورودی</td><td>تعداد صفحه‌هایی از همین سایت که به این صفحه لینک داده‌اند.</td></tr>
          <tr><td>کلمات، آخرین ویرایش</td><td>طول متن و تاریخ ویرایش.</td></tr>
        </tbody>
      </table></div>
      <p>فیلترها: پروژه، مشکل، نوع، وضعیت و «فقط صفحه‌های دارای مشکل». دکمه‌ی «تحلیل دوباره» همه‌چیز را همین حالا دوباره می‌سنجد (تحلیل خودکار بعد از هر دریافت و هر صبح ۰۷:۳۰ هم اجرا می‌شود). «بررسی ایندکس» وضعیت صفحه در گوگل را می‌پرسد.</p>
      <p><b>مشکل‌ها چه معنی دارند و چه کنیم:</b></p>
      <div class="tbl"><table class="ref">
        <thead><tr><th>برچسب</th><th>یعنی</th><th>راه‌حل</th></tr></thead>
        <tbody>
          <tr><td>noindex فعال است</td><td>صفحه به گوگل می‌گوید «ایندکسم نکن».</td><td>اگر عمدی نیست، در Yoast/Rank Math برش دارید.</td></tr>
          <tr><td>بدون نمایش در گوگل</td><td>صفحه‌ای قدیمی‌تر از ۳۰ روز با صفر ایمپرشن.</td><td>ایندکس را بررسی کنید؛ لینک داخلی بدهید؛ کیفیت و یکتایی محتوا را ببینید.</td></tr>
          <tr><td>افت ترافیک</td><td>صفحه‌ای که قبلاً دست‌کم ۲۰ کلیک داشت و حالا به ۶۰٪ یا کمتر رسیده.</td><td>تغییرهای اخیر و رقبا را بررسی و محتوا را تازه کنید.</td></tr>
          <tr><td>یتیم</td><td>هیچ صفحه‌ای به آن لینک نداده.</td><td>از دو سه نوشته‌ی هم‌موضوع لینک بدهید.</td></tr>
          <tr><td>بدون توضیح متا</td><td>توضیح سئو (Meta description) خالی است.</td><td>یک یا دو جمله‌ی جذاب که دلیل کلیک بدهد.</td></tr>
          <tr><td>بدون کلمه کانونی</td><td>برای نوشته یا محصول کلمه‌ی کانونی تعیین نشده.</td><td>در Yoast/Rank Math تعیین کنید.</td></tr>
          <tr><td>CTR پایین در صفحه اول</td><td>رتبه‌ی ۱۰ یا بهتر، حداقل ۲۰۰ ایمپرشن و CTR زیر ۱٪.</td><td>عنوان و توضیح را بازنویسی کنید.</td></tr>
          <tr><td>محتوای کم</td><td>مقاله‌ی زیر ۳۰۰ کلمه.</td><td>محتوا را کامل‌تر کنید یا با مقاله‌ی نزدیک ادغام کنید.</td></tr>
          <tr><td>تصویر بدون alt</td><td>تصویری بدون متن جایگزین.</td><td>برای تصاویر مهم alt بنویسید.</td></tr>
          <tr><td>نزدیک به صفحه اول</td><td>رتبه‌ی ۱۱ تا ۲۰ با دست‌کم ۵۰ ایمپرشن (نشان مثبت، رنگ سبز).</td><td>کمی تقویت = ورود به صفحه‌ی اول.</td></tr>
          <tr><td>ترافیک بدون تبدیل</td><td>دست‌کم ۱۰۰ نشست و صفر تبدیل، در سایتی که تبدیل ثبت می‌کند.</td><td>دکمه یا فرم صفحه، و درستی تعریف تبدیل در GA4 را بررسی کنید.</td></tr>
        </tbody>
      </table></div>

      <p><b>وضعیت سایت‌های وردپرسی</b> — <b>مرکز سئو ← وضعیت سایت‌های وردپرسی</b>: یک برد برای همه‌ی سایت‌ها، بدترین‌ها بالا. با کلیک روی هر وضعیت (دکمه‌های بالای صفحه) فقط همان سایت‌ها را می‌بینید.</p>
      <div class="tbl"><table class="ref">
        <thead><tr><th>وضعیت</th><th>یعنی</th><th>کار شما</th></tr></thead>
        <tbody>
          <tr><td>سایت در دسترس نیست</td><td>دو بررسی پشت‌سرهم (هر ۱۵ دقیقه) شکست خورده.</td><td>هاست و دامنه را فوری ببینید.</td></tr>
          <tr><td>خطا در دریافت</td><td>پلاگین پیام فرستاده ولی سامانه نتوانسته ذخیره کند؛ خطا زیر وضعیت نوشته می‌شود.</td><td>متن خطا را بخوانید (بخش ۱۷).</td></tr>
          <tr><td>ساکت</td><td>بیش از ۱۲ ساعت از پلاگین خبری نیست.</td><td>پلاگین غیرفعال شده؟ کرون وردپرس کار می‌کند؟ سایت بالاست؟</td></tr>
          <tr><td>SSL رو به پایان</td><td>گواهی SSL کمتر از ۱۴ روز مانده یا منقضی شده (روزها زیر وضعیت نوشته می‌شود).</td><td>گواهی را تمدید کنید.</td></tr>
          <tr><td>موتور جست‌وجو مسدود است</td><td>گزینه‌ی «جلوگیری از ایندکس‌شدن» وردپرس روشن است (معمولاً بعد از ساخت سایت فراموش می‌شود).</td><td>وردپرس ← تنظیمات ← خواندن؛ تیک را بردارید.</td></tr>
          <tr><td>وردپرس یا افزونه‌ها قدیمی</td><td>نسخه‌ی تازه‌ی وردپرس یا افزونه‌ای آماده است (نام و نسخه‌ی افزونه‌ها در «جزئیات»).</td><td>به‌روزرسانی (پس از پشتیبان‌گیری).</td></tr>
          <tr><td>پلاگین قدیمی</td><td>نسخه‌ی افزونه‌ی CRM روی سایت از نسخه‌ی موجود در سامانه عقب‌تر است.</td><td>معمولاً خودش به‌روز می‌شود؛ اگر نشد، در وردپرس «به‌روزرسانی» بزنید.</td></tr>
          <tr><td>در انتظار اتصال / قطع‌شده</td><td>کد اتصال هنوز استفاده نشده یا اتصال قطع شده.</td><td>بخش ۴.</td></tr>
          <tr><td>سالم</td><td>هیچ‌کدام از موارد بالا نیست.</td><td>—</td></tr>
        </tbody>
      </table></div>
      <p>روی «جزئیات» هر ردیف بزنید: آمار Analytics، نسخه‌ی قدیمی وردپرس، افزونه‌های قدیمی، پربازدیدترین آدرس‌های «۴۰۴» (برای ساختن ریدایرکت) و عبارت‌هایی که در جست‌وجوی داخل سایت چیزی پیدا نکرده‌اند.</p>

      <h3 id="seo-10">۱۰. خلأ پوشش محتوا و بریف</h3>
      <p>
        <b>چرا:</b> مهم‌ترین سؤال تولید محتوا این است: «چه بنویسیم؟». این صفحه جواب را از مقایسه‌ی «گوگل چه چیزی را
        به سایت شما نشان می‌دهد» با «سایت شما چه صفحه‌هایی دارد» می‌دهد. نیاز به Search Console وصل‌شده و پلاگین وردپرس دارد.
      </p>
      <ol class="steps">
        <li><b>۱.</b> <b>مرکز سئو ← خلأ پوشش محتوا</b> را باز کنید و پروژه را انتخاب کنید.</li>
        <li><b>۲.</b> «حداقل ایمپرشن در ۲۸ روز» را تنظیم کنید (۵، ۱۰، ۳۰، ۵۰ یا ۱۰۰؛ پیش‌فرض ۱۰). برای سایت کوچک عدد کم بگذارید، چون هر عبارت ایمپرشن کمی دارد.</li>
        <li><b>۳.</b> سه جدول می‌بینید (هر کدام تا ۵۰ ردیف):</li>
      </ol>
      <div class="tbl"><table class="ref">
        <thead><tr><th>جدول</th><th>چه ردیف‌هایی</th><th>کار</th></tr></thead>
        <tbody>
          <tr><td><b>مقاله‌ی تازه بنویسید</b></td><td>عبارتی که گوگل سایت را برایش نشان می‌دهد، بیرون ده نتیجه‌ی اول است، هیچ صفحه‌ای هدفش نیست و در تقویم هم نیست. «هدف نبودن» یعنی نه کلمه‌ی کانونی هیچ صفحه‌ای آن است و نه عنوان صفحه‌ای همه‌ی کلمه‌های آن را دارد (پسوندهای رایج فارسی مثل «ها» و «ی» نادیده گرفته می‌شود: «راهنما» و «راهنمای» یکی است).</td><td>مقاله‌ی تازه.</td></tr>
          <tr><td><b>این مقاله‌ها را بازنویسی کنید</b></td><td>صفحه‌ای از خود سایت برای آن عبارت در رتبه‌ی ۱۱ تا ۳۰ است.</td><td>همان صفحه را بهتر کنید؛ سریع‌تر از نوشتن مقاله‌ی تازه جواب می‌دهد.</td></tr>
          <tr><td><b>در خود سایت جست‌وجو شد و چیزی پیدا نشد</b></td><td>عبارتی که بازدیدکننده‌ها در جست‌وجوی داخل سایت تایپ کرده‌اند و دست‌کم دو بار نتیجه‌ای نیامده (نیاز به مجوز «عبارت‌های جست‌وجوی داخل سایت» در پلاگین).</td><td>محتوایی که مخاطب می‌خواهد و ندارید.</td></tr>
        </tbody>
      </table></div>
      <ol class="steps" start="4">
        <li><b>۴.</b> برای هر ردیف <b>«افزودن به تقویم»</b> را بزنید. یک آیتم در تقویم محتوا ساخته می‌شود: برای مقاله‌ی تازه با وضعیت «ایده» و برای بازنویسی با وضعیت «نیاز به بروزرسانی» و عنوان «بازنویسی: …».</li>
      </ol>
      <p><b>بریف:</b> در «یادداشت‌ها»ی همان آیتم، سامانه خودش یک بریف برای نویسنده می‌نویسد (بدون هوش مصنوعی، فقط از داده‌ی واقعی):</p>
      <ul>
        <li>دلیل پیشنهاد (مثلاً رتبه‌ی فعلی، یا اینکه چند بار در سایت جست‌وجو شده).</li>
        <li>برای بازنویسی: آدرس صفحه‌ی فعلی، تعداد کلمات، تعداد سرتیترها، لینک‌های داخلی ورودی، آخرین ویرایش و مشکل‌های آن صفحه.</li>
        <li><b>لینک داخلی:</b> صفحه‌هایی از همین سایت با موضوع مشترک که باید در متن به آن‌ها لینک بدهید و از آن‌ها هم به این مقاله لینک بگیرید.</li>
        <li>عبارت‌های هم‌خانواده که گوگل همین حالا به سایت نشان می‌دهد تا در متن بیاورید.</li>
        <li>آنچه مردم در جعبه‌ی جست‌وجوی گوگل برای این موضوع تایپ می‌کنند (سؤال‌ها و بخش‌های پیشنهادی مقاله)؛ اگر دسترسی سرور به گوگل برقرار نباشد این بخش خالی می‌ماند.</li>
      </ul>
      <p>اگر صفحه‌ی خلأ پوشش خالی بود، خودش دلیل را می‌نویسد: «محتوای وردپرس نرسیده»، «فهرست کلمات کلیدی هنوز ساخته نشده (تحلیل الان را بزنید)» یا «هیچ عبارتی به حداقل ایمپرشن نرسیده (عدد را پایین بیاورید)».</p>

      <h3 id="seo-11">۱۱. تقویم محتوا: از ایده تا انتشار و اندازه‌گیری</h3>
      <p>
        <b>چرا:</b> تقویم محتوا محل رسیدن همه‌ی راه‌هاست: فرصت‌ها، پیشنهادهای کلمه، خلأ پوشش. هر آیتم مسئول و مهلت دارد و تا
        انتشار و اندازه‌گیری دنبال می‌شود (<b>مرکز سئو ← تقویم محتوا</b>).
      </p>
      <div class="tbl"><table class="ref">
        <thead><tr><th>فیلد</th><th>توضیح</th></tr></thead>
        <tbody>
          <tr><td>پروژه، عنوان، کلمه کلیدی هدف</td><td>عنوان کار و عبارتی که مقاله باید برایش رتبه بگیرد.</td></tr>
          <tr><td>نوع محتوا</td><td>متن آزاد: «مقاله وبلاگ»، «صفحه فرود»، «بروزرسانی محتوای موجود» و مانند آن.</td></tr>
          <tr><td>وضعیت</td><td>ایده ← برنامه‌ریزی‌شده ← در حال نگارش ← منتشرشده؛ و «نیاز به بروزرسانی» برای کار روی مقاله‌ی موجود.</td></tr>
          <tr><td>مسئول نگارش، مهلت</td><td>چه کسی و تا کی. آیتم‌های نزدیک به مهلت در داشبورد سئو دیده می‌شوند.</td></tr>
          <tr><td>آدرس نهایی منتشرشده، تاریخ انتشار</td><td>بعد از انتشار پر می‌شود و برای اندازه‌گیری لازم است.</td></tr>
          <tr><td>یادداشت‌ها</td><td>بریف خودکار و یادداشت تیم.</td></tr>
        </tbody>
      </table></div>
      <p><b>دکمه‌های هر ردیف:</b></p>
      <div class="tbl"><table class="ref">
        <thead><tr><th>دکمه</th><th>چه می‌کند</th></tr></thead>
        <tbody>
          <tr><td>شروع نگارش</td><td>وضعیت را «در حال نگارش» می‌کند (برای «ایده» و «برنامه‌ریزی‌شده»).</td></tr>
          <tr><td>انتشار</td><td>آدرس نهایی را می‌پرسد و آیتم را «منتشرشده» با تاریخ امروز علامت می‌زند.</td></tr>
          <tr><td>عملکرد</td><td>برای آیتم منتشرشده: کلیک، ایمپرشن و رتبه‌ی همان صفحه از روز انتشار تاکنون.</td></tr>
          <tr><td>بررسی ایندکس</td><td>از گوگل می‌پرسد صفحه‌ی تازه را دیده یا نه (چون ترافیک آن هنوز هفته‌ها طول می‌کشد تا دیده شود).</td></tr>
          <tr><td>پیشنهاد زمان‌بندی (بالای صفحه)</td><td>پروژه و بازه‌ی زمانی (و تعداد اختیاری) را می‌دهید؛ سامانه بلافاصله برای آیتم‌های بدون مهلت و پیشنهادهای تأییدشده‌ی هنوز تبدیل‌نشده مهلت‌های یکنواخت می‌گذارد و فهرست تاریخ‌ها را نشان می‌دهد. اگر تعداد خالی بماند، از سرعت انتشار سه ماه اخیر پروژه محاسبه می‌شود، یا ۴ مورد در ماه اگر سابقه‌ای نباشد.</td></tr>
        </tbody>
      </table></div>
      <div class="callout">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round"><path d="M5 12l4 4 10-10"/></svg>
        <div>
          <span class="label">«منتشرشده» خودکار هم می‌شود</span>
          <p>اگر پلاگین وردپرس وصل باشد، لازم نیست حتماً خودتان «انتشار» را بزنید. سامانه بعد از هر دریافت (و هر صبح) آیتم‌های هنوز منتشرنشده را با صفحه‌های وردپرس می‌سنجد. <b>مقاله‌ی تازه:</b> اگر بعد از ساخت آیتم صفحه‌ای منتشر شود که کلمه‌ی کانونی‌اش همان کلمه‌ی هدف است (یا عنوانش همه‌ی کلمه‌های آن را دارد)، آیتم «منتشرشده» می‌شود و آدرس و تاریخ را می‌گیرد. <b>بازنویسی:</b> اگر همان صفحه بعد از ساخت آیتم ویرایش شود، آیتم تمام‌شده حساب می‌شود. هر صفحه فقط به یک آیتم داده می‌شود. محدودیت: ویرایش کوچک و بی‌ربط (مثلاً غلط تایپی) را از بازنویسی واقعی تشخیص نمی‌دهد.</p>
        </div>
      </div>

      <h3 id="seo-12">۱۲. ممیزی فنی</h3>
      <p>
        <b>چرا:</b> گاهی مشکل از محتوا نیست؛ سایت خراب است (لینک شکسته، صفحه‌ی کند، عنوان تکراری). ممیزی فنی چند ده صفحه‌ی
        سایت را مثل یک ربات گوگل می‌خزد و مشکل‌ها را بر اساس شدت فهرست می‌کند.
      </p>
      <ol class="steps">
        <li><b>۱.</b> <b>مرکز سئو ← ممیزی فنی سئو ← اجرای بررسی جدید</b> را بزنید و پروژه را انتخاب کنید. (خودکار هم هر جمعه ساعت ۰۳:۰۰ صبح اجرا می‌شود.) بررسی تا ۸۰ صفحه را می‌خزد.</li>
        <li><b>۲.</b> وضعیت از «در حال اجرا» به «تکمیل‌شده» (یا «ناموفق») تغییر می‌کند. ستون‌ها: امتیاز کلی، امتیاز سرعت موبایل (PageSpeed Insights؛ نیاز به API Key بخش ۱)، تعداد مشکلات، صفحات بررسی‌شده.</li>
        <li><b>۳.</b> <b>«مشاهده مشکلات»</b> را بزنید. هر مشکل شدت دارد: «بحرانی»، «هشدار» یا «اطلاعاتی». اول بحرانی‌ها.</li>
        <li><b>۴.</b> بعد از رفع، یک بررسی تازه اجرا کنید و امتیاز را با قبلی مقایسه کنید.</li>
      </ol>
      <div class="tbl"><table class="ref">
        <thead><tr><th>گروه</th><th>مشکل‌هایی که پیدا می‌شود</th></tr></thead>
        <tbody>
          <tr><td>عنوان و توضیح</td><td>بدون عنوان (title)، عنوان تکراری، بدون توضیحات متا.</td></tr>
          <tr><td>سرتیترها</td><td>بدون H1، چند H1 روی یک صفحه، H1 تکراری.</td></tr>
          <tr><td>ساختار و لینک</td><td>لینک خراب، زنجیره‌ی ریدایرکت طولانی (۳ پرش یا بیشتر)، صفحه‌ی یتیم، بدون canonical.</td></tr>
          <tr><td>سرعت و ظاهر</td><td>صفحه‌ی کند (بیش از ۳ ثانیه)، بدون viewport (ریسپانسیو نبودن)، تصویر بدون alt، تصویر بدون ابعاد (width/height).</td></tr>
          <tr><td>محتوا</td><td>محتوای کم‌حجم (زیر ۱۰۰ کلمه)، محتوای تکراری.</td></tr>
          <tr><td>فایل‌های پایه</td><td>نبود robots.txt، نبود sitemap.xml.</td></tr>
          <tr><td>داده‌ی ساخت‌یافته و شبکه‌های اجتماعی</td><td>بدون Schema/JSON-LD، بدون Schema کسب‌وکار محلی (برای کسب‌وکارهای محلی)، بدون Open Graph (پیش‌نمایش ضعیف در واتساپ و شبکه‌های اجتماعی)، شماره‌ی تماس ناهماهنگ.</td></tr>
        </tbody>
      </table></div>
      <p>مشکل‌های ممیزی در «صف اقدام» (بخش ۷) هم کنار فرصت‌ها می‌آیند.</p>

      <h3 id="seo-13">۱۳. هشدارها، تلگرام و پایش آنلاین‌بودن</h3>
      <p>
        <b>چرا:</b> قرار نیست هر روز همه‌جا سر بزنید. سامانه خودش خبر می‌دهد، آن هم فقط وقتی چیز تازه‌ای شده (نه هر ربع ساعت
        برای مشکلی که هنوز هست).
      </p>
      <p><b>تنظیم هشدار</b> — <b>مرکز سئو ← تنظیمات هشدار سئو</b>:</p>
      <div class="tbl"><table class="ref">
        <thead><tr><th>گزینه</th><th>توضیح</th></tr></thead>
        <tbody>
          <tr><td>فعال بودن هشدارها</td><td>کلید اصلی؛ تا روشن نباشد هیچ هشداری (حتی تلگرام) نمی‌رود.</td></tr>
          <tr><td>آستانه‌ی افت رتبه</td><td>اگر رتبه‌ی یک کلمه‌ی ردیابی‌شده به این تعداد (پیش‌فرض ۵) یا بیشتر افت کند، هشدار می‌دهد.</td></tr>
          <tr><td>کانال‌ها</td><td>اعلان فوری (Web Push)، پیامک، ایمیل و تلگرام.</td></tr>
          <tr><td>کاربران دریافت‌کننده</td><td>برای اعلان فوری، پیامک و ایمیل. تلگرام به یک گفتگوی مشخص می‌رود و به این فهرست نیاز ندارد.</td></tr>
        </tbody>
      </table></div>
      <p><b>چه چیزهایی هشدار می‌دهد:</b></p>
      <ul>
        <li>فرصت سئوی با اولویت «بالا» (فقط همان لحظه‌ی شناسایی).</li>
        <li>رقیبی که صفحه‌ی تازه منتشر کرده.</li>
        <li>قطع اتصال Search Console (فقط بار اولی که وضعیت از «متصل» به «خطا» می‌رود).</li>
        <li><b>سایت در دسترس نیست</b>، <b>دوباره در دسترس است</b> و <b>گواهی SSL نزدیک انقضا</b>.</li>
        <li>افت رتبه‌ی کلمه‌ی ردیابی‌شده: این هشدار به ترکر کلمه‌ی کلیدی وابسته است که فعلاً متوقف است (بخش ۱۹)، پس عملاً هشداری نمی‌رسد.</li>
      </ul>
      <p><b>راه‌اندازی هشدار تلگرام (حدود ۵ دقیقه):</b></p>
      <ol class="steps">
        <li><b>۱.</b> در تلگرام با &#64;BotFather گفتگو کنید، دستور <span class="mono">/newbot</span> را بزنید و نام بات را بدهید. توکنی شبیه <span class="mono">123456:ABC...</span> می‌دهد؛ آن را نگه دارید.</li>
        <li><b>۲.</b> بات را به گروه یا کانال تیم اضافه کنید (یا فقط به بات پیام بدهید تا برای خودتان کار کند).</li>
        <li><b>۳.</b> «شناسه‌ی گفتگو (Chat ID)» را بگیرید؛ برای گروه معمولاً عددی منفی است.</li>
        <li><b>۴.</b> در «تنظیمات هشدار سئو»: «فعال بودن هشدارها» و «تلگرام» را روشن کنید، توکن و شناسه را بنویسید و ذخیره کنید. توکن ذخیره‌شده دوباره نمایش داده نمی‌شود؛ خالی گذاشتنش یعنی «عوض نکن».</li>
        <li><b>۵.</b> اگر سرور شما به تلگرام دسترسی ندارد (در بعضی هاست‌های ایرانی)، در «آدرس API تلگرام» آدرس یک رله‌ی (پروکسی) تلگرام را بگذارید.</li>
        <li><b>۶.</b> دکمه‌ی بالای صفحه <b>«ارسال پیام آزمایشی به تلگرام»</b> را بزنید. اگر پیام نرسید، پیام خطا راهنمایی می‌کند.</li>
      </ol>
      <p><b>پایش آنلاین‌بودن و SSL چطور کار می‌کند؟</b> هر ۱۵ دقیقه سامانه آدرس سایت همه‌ی پروژه‌های «فعال» را باز می‌کند. پاسخ HTTP کمتر از ۵۰۰ یعنی سایت بالاست (حتی ۴۰۳). اگر <b>دو بار پشت‌سرهم</b> جواب ندهد، «در دسترس نیست» اعلام می‌شود و وقتی برگشت «دوباره در دسترس است». گواهی SSL هم وقتی ۱۴، ۷، ۳ و ۱ روز مانده باشد هر کدام فقط یک بار یادآوری می‌شود و بعد از تمدید دوباره از اول حساب می‌شود. وضعیت همه‌ی سایت‌ها را در برد وضعیت (بخش ۹) هم می‌بینید.</p>

      <h3 id="seo-14">۱۴. گزارش دوره‌ای مدیریتی</h3>
      <p><b>چرا:</b> برای ارائه به مدیر یا مشتری یک خلاصه‌ی چاپ‌پذیر لازم است: «این ماه چه شد و چه کردیم».</p>
      <ol class="steps">
        <li><b>۱.</b> <b>مرکز سئو ← گزارش دوره‌ای مدیریتی ← تولید گزارش</b> را بزنید.</li>
        <li><b>۲.</b> پروژه و بازه را انتخاب کنید (پیش‌فرض: ماه گذشته).</li>
        <li><b>۳.</b> <b>«مشاهده / چاپ»</b> را بزنید؛ صفحه‌ای مناسب چاپ (یا ذخیره به‌صورت PDF از مرورگر) باز می‌شود.</li>
      </ol>
      <p>گزارش، عملکرد جست‌وجو، فرصت‌ها، محتوا و ممیزی‌های فنی را در یک صفحه جمع می‌کند و همان لحظه‌ی تولید ثبت می‌شود؛ پس عددها بعداً عوض نمی‌شوند. چون گوگل داده‌ی سه روز آخر را هنوز نهایی نکرده، پایان بازه‌ی گزارش به همین اندازه عقب کشیده می‌شود تا یک دوره‌ی ناقص با دوره‌ی کامل مقایسه نشود. هر ماه روز اول ساعت ۰۴:۰۰ گزارش ماه گذشته برای همه‌ی پروژه‌ها خودکار ساخته می‌شود.</p>

      <h3 id="seo-15">۱۵. برنامه‌ی کار پیشنهادی</h3>
      <p>اگر فقط یک بخش از این فصل را بخوانید، همین را بخوانید. چرخه‌ی کار را به ریتم تبدیل کنید:</p>
      <div class="tbl"><table class="ref">
        <thead><tr><th>تناوب</th><th>چه کنیم (به ترتیب)</th><th>زمان</th></tr></thead>
        <tbody>
          <tr><td><b>یک‌بار برای هر سایت</b></td><td>۱) تعریف پروژه با «خدمات واقعی» دقیق (بخش ۲). ۲) اتصال Search Console، انتخاب ملک، همگام‌سازی (۳). ۳) نصب پلاگین و اتصال با کد (۴). ۴) اتصال Analytics در صورت نیاز (۵). ۵) «طبقه‌بندی هوشمند کلمات» و «کشف کلمات کلیدی جدید». ۶) چک‌لیست پایان فصل.</td><td>حدود ۲۰ دقیقه</td></tr>
          <tr><td><b>هر روز</b></td><td>۱) برد «وضعیت سایت‌های وردپرسی»: سایت قرمز یا ساکت هست؟ ۲) «صف اقدام»: بالاترین مورد را انجام دهید. ۳) «تقویم محتوا»: مهلت‌های نزدیک، «شروع نگارش»، «انتشار». ۴) اگر هشدار تلگرام آمده، همان را بررسی کنید.</td><td>حدود ۱۰ دقیقه</td></tr>
          <tr><td><b>هفته‌ای یک بار</b></td><td>۱) «خلأ پوشش محتوا»: چند ردیف «افزودن به تقویم». ۲) «پیشنهادهای کلمات کلیدی»: تأیید و رد. ۳) «محتوای سایت (وردپرس)» با فیلتر «فقط صفحه‌های دارای مشکل»: چند مورد ساده را درست کنید. ۴) فرصت‌هایی که کارشان انجام شده را «برطرف شد» بزنید. ۵) نتیجه‌ی ممیزی فنی جمعه را ببینید.</td><td>حدود ۳۰ دقیقه</td></tr>
          <tr><td><b>ماهی یک بار</b></td><td>۱) «نتیجه اقدام» فرصت‌های برطرف‌شده: چه کاری جواب داد؟ ۲) «عملکرد» آیتم‌های منتشرشده. ۳) گزارش ماه را ببینید و چاپ کنید. ۴) «پیشنهاد زمان‌بندی» برای ماه بعد. ۵) فهرست «کلمات برند» پروژه را به‌روز کنید.</td><td>حدود ۱ ساعت</td></tr>
        </tbody>
      </table></div>
      <p><b>طرح چهار جلسه برای آموزش یک همکار تازه:</b></p>
      <ol class="steps">
        <li><b>جلسه‌ی ۱ — مفاهیم و راه‌اندازی:</b> بخش ۰ (واژه‌ها) و بعد با هم یک پروژه تعریف و Search Console و پلاگین را وصل کنید (بخش‌های ۲ تا ۴).</li>
        <li><b>جلسه‌ی ۲ — خواندن داده:</b> داشبورد، کلمات کلیدی، فرصت‌ها و صف اقدام (بخش‌های ۶ و ۷). تمرین: یک فرصت را بررسی کنید و «مشاهده شواهد» را برای همکار توضیح دهید.</li>
        <li><b>جلسه‌ی ۳ — تولید محتوا:</b> خلأ پوشش، بریف و تقویم (بخش‌های ۸، ۱۰، ۱۱). تمرین: دو ردیف به تقویم اضافه کنید و بریفشان را بخوانید.</li>
        <li><b>جلسه‌ی ۴ — اندازه‌گیری و پایش:</b> «برطرف شد» و نتیجه‌ی ۲۸ روزه، ممیزی فنی، هشدار تلگرام و گزارش (بخش‌های ۷، ۱۲، ۱۳، ۱۴).</li>
      </ol>
      <div class="callout">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round"><path d="M12 3v3M12 18v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M3 12h3M18 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/><circle cx="12" cy="12" r="4"/></svg>
        <div>
          <span class="label">از دستیار هم بپرسید</span>
          <p>برای سؤال درباره‌ی وضعیت سئوی هر پروژه می‌توانید از «دستیار هوشمند مدیریتی» هم بپرسید؛ جدول کامل آن در فصل مربوط به دستیار است.</p>
        </div>
      </div>

      <h3 id="seo-16">۱۶. مدیریت چند سایت (مثلاً صد سایت)</h3>
      <ul>
        <li><b>یک سایت = یک پروژه.</b> نام را یکدست بنویسید (مثلاً «نام برند - دامنه»)، چون همه‌ی فهرست‌ها و هشدارها با همین نام نشان داده می‌شوند.</li>
        <li><b>هر روز فقط برد را ببینید.</b> «وضعیت سایت‌های وردپرسی» بدترین سایت‌ها را بالا می‌آورد؛ سایت سالم نیاز به توجه ندارد. اول قرمزها (در دسترس نیست، خطا، ساکت)، بعد زردها (SSL، قدیمی).</li>
        <li><b>کد اتصال ۲۴ ساعت اعتبار دارد.</b> برای راه‌اندازی دسته‌ای، هر سایت را همان روز که کد گرفتید وصل کنید.</li>
        <li><b>پلاگین خودش به‌روز می‌شود</b> (اگر مدیر سایت تیک را برنداشته باشد)؛ برای صد سایت لازم نیست zip را دوباره نصب کنید. ستون «پلاگین قدیمی» در برد نشان می‌دهد کدام سایت عقب مانده.</li>
        <li><b>فشار روی هاست پخش می‌شود:</b> تحلیل شبانه‌ی هر سایت وردپرسی و دریافت آمار Analytics با فاصله‌ی چند ثانیه از هم در صف می‌روند، و پایش آنلاین‌بودن سایت‌ها را دسته‌دسته (۱۰ تایی) چک می‌کند.</li>
        <li><b>نقش‌ها را جدا کنید:</b> مدیر سئو همه‌ی منوهای گروه «مرکز سئو» را می‌بیند؛ نویسنده فقط به «تقویم محتوا» و در صورت نیاز «خلأ پوشش محتوا» دسترسی بدهید (فصل ۱۱).</li>
        <li><b>تلگرام را یک گروه مشترک کنید</b> تا همه‌ی هشدارهای همه‌ی سایت‌ها یک‌جا برسد.</li>
      </ul>

      <h3 id="seo-17">۱۷. عیب‌یابی: علامت، علت، راه‌حل</h3>
      <div class="tbl"><table class="ref">
        <thead><tr><th>علامت</th><th>علت محتمل</th><th>راه‌حل</th></tr></thead>
        <tbody>
          <tr><td>«اتصال گوگل تنظیم نشده است»</td><td>Client ID/Secret وارد نشده.</td><td>بخش ۱، مرحله‌ی ۵ و ۶.</td></tr>
          <tr><td>گوگل «redirect_uri_mismatch» می‌دهد</td><td>آدرس بازگشت در Google Cloud دقیقاً همان نیست.</td><td>آدرس را از «تنظیمات اتصال گوگل» عیناً کپی و در Authorized redirect URIs بگذارید.</td></tr>
          <tr><td>ملک سایت در فهرست نیست</td><td>حساب گوگلِ وصل‌شده در Search Console آن سایت دسترسی ندارد.</td><td>با حساب درست دوباره وصل کنید یا دسترسی بگیرید.</td></tr>
          <tr><td>وضعیت «نیاز به اتصال مجدد»</td><td>نشست گوگل منقضی شده یا دسترسی پس گرفته شده.</td><td>«اتصال Google Search Console» را دوباره بزنید.</td></tr>
          <tr><td>کلمات کلیدی خالی است</td><td>همگام‌سازی هنوز نشده، تحلیل هنوز اجرا نشده، یا سایت تازه است و گوگل هنوز داده‌ای ندارد.</td><td>«همگام‌سازی الان» و بعد «تحلیل الان»؛ چند دقیقه صبر کنید. سایت بسیار تازه ممکن است هفته‌ها داده نداشته باشد.</td></tr>
          <tr><td>«خلأ پوشش محتوا» خالی است</td><td>صفحه خودش دلیل را می‌نویسد.</td><td>«محتوای وردپرس نرسیده» ← بخش ۴؛ «فهرست کلمات ساخته نشده» ← «تحلیل الان»؛ «هیچ عبارتی به حداقل ایمپرشن نرسیده» ← عدد را پایین بیاورید.</td></tr>
          <tr><td>پلاگین «کد اشتباه یا منقضی» می‌گوید</td><td>کد ۲۴ ساعت گذشته یا قبلاً استفاده شده.</td><td>در سامانه دوباره «اتصال وردپرس» بزنید و کد تازه بدهید.</td></tr>
          <tr><td>«محتوای سایت (وردپرس)» خالی است</td><td>پلاگین هنوز چیزی نفرستاده، یا مجوز «فهرست محتوا» یا نوع محتوا درست نیست.</td><td>در پلاگین «همگام‌سازی همین حالا» را بزنید و «آخرین ارتباط‌ها» را ببینید.</td></tr>
          <tr><td>وضعیت «خطا در دریافت»</td><td>سامانه هنگام ذخیره‌ی داده با مشکل روبه‌رو شده؛ متن خطا زیر وضعیت نوشته می‌شود.</td><td>متن را بخوانید. اگر درباره‌ی «Data too long» بود، یعنی دیپلوی (مهاجرت‌ها) هنوز اجرا نشده؛ بعد از دیپلوی در پلاگین «ارسال دوباره‌ی همه از ابتدا» را بزنید.</td></tr>
          <tr><td>وضعیت «ساکت»</td><td>بیش از ۱۲ ساعت از پلاگین خبری نرسیده.</td><td>پلاگین فعال است؟ سایت بالاست؟ کرون وردپرس کار می‌کند؟ (ورود مدیر به پیشخوان هم همگام‌سازی را راه می‌اندازد.)</td></tr>
          <tr><td>دکمه‌ی «انتخاب ویژگی GA4» نیست</td><td>این اتصال اجازه‌ی Analytics ندارد.</td><td>«اتصال مجدد برای Google Analytics» و تیک زدن گزینه‌ی Analytics.</td></tr>
          <tr><td>خطای Analytics مثل «API has not been used»</td><td>Data API یا Admin API در پروژه‌ی گوگل فعال نیست.</td><td>در Google Cloud هر دو را Enable کنید و «همگام‌سازی Analytics الان» را بزنید.</td></tr>
          <tr><td>پیام تلگرام نمی‌رسد</td><td>هشدارها یا تلگرام روشن نیست، توکن/شناسه اشتباه است، یا سرور به تلگرام دسترسی ندارد.</td><td>«ارسال پیام آزمایشی» را بزنید؛ در صورت نیاز آدرس رله بدهید.</td></tr>
          <tr><td>بریف بخش «آنچه مردم در گوگل تایپ می‌کنند» ندارد</td><td>سرور به سرویس پیشنهاد جست‌وجوی گوگل دسترسی ندارد یا گوگل موقتاً مسدود کرده.</td><td>بقیه‌ی بریف کامل است؛ بعداً دوباره امتحان می‌شود.</td></tr>
          <tr><td>ممیزی فنی «ناموفق» است</td><td>سایت پاسخ نداده یا فایروال ربات را بسته.</td><td>در دسترس بودن سایت را بررسی کنید و دوباره اجرا کنید.</td></tr>
          <tr><td>منوی مرکز سئو یا یکی از صفحه‌ها را نمی‌بینم</td><td>نقش شما به آن دسترسی ندارد.</td><td>از مدیر بخواهید دسترسی نقش را بدهد (فصل ۱۱).</td></tr>
          <tr><td>پس از زدن دکمه‌ی «الان» چیزی نشد</td><td>کارها از صف اجرا می‌شوند و گاهی تا یک دقیقه طول می‌کشد.</td><td>صبر کنید و صفحه را تازه کنید.</td></tr>
        </tbody>
      </table></div>

      <h3 id="seo-18">۱۸. کارهای خودکار سامانه</h3>
      <p>همه به وقت تهران هستند و از کرون سرور اجرا می‌شوند؛ بعد از اتصال لازم نیست هیچ‌کدام را دستی بزنید.</p>
      <div class="tbl"><table class="ref">
        <thead><tr><th>کار</th><th>زمان</th></tr></thead>
        <tbody>
          <tr><td>همگام‌سازی Search Console (و بلافاصله بعد از آن تحلیل کلمات و فرصت‌ها)</td><td>هر شب ۰۱:۰۰</td></tr>
          <tr><td>دریافت آمار Google Analytics</td><td>هر شب ۰۲:۰۰</td></tr>
          <tr><td>سنجش نتیجه‌ی فرصت‌های برطرف‌شده (پس از ۲۸ روز)</td><td>هر شب ۰۲:۰۰</td></tr>
          <tr><td>ممیزی فنی</td><td>جمعه‌ها ۰۳:۰۰</td></tr>
          <tr><td>تحلیل شکاف رقبا</td><td>شنبه‌ها ۰۳:۰۰</td></tr>
          <tr><td>همگام‌سازی Sitemap</td><td>هر شب ۰۵:۰۰</td></tr>
          <tr><td>تحلیل محتوای وردپرس (مشکل‌ها، لینک داخلی، تطبیق با تقویم)</td><td>هر صبح ۰۷:۳۰ و حدود دو دقیقه بعد از هر دریافت از پلاگین</td></tr>
          <tr><td>پایش آنلاین‌بودن و SSL</td><td>هر ۱۵ دقیقه</td></tr>
          <tr><td>گزارش دوره‌ای ماه گذشته</td><td>روز اول هر ماه ۰۴:۰۰</td></tr>
          <tr><td>ارسال از پلاگین وردپرس به سامانه</td><td>هر ۱۰ دقیقه و ۳۰ ثانیه پس از ذخیره‌ی هر نوشته</td></tr>
        </tbody>
      </table></div>

      <h3 id="seo-19">۱۹. محدودیت‌ها و چک‌لیست پایانی</h3>
      <p><b>چیزهایی که باید بدانید (صادقانه):</b></p>
      <ul>
        <li><b>رتبه‌ی لحظه‌ای ندارید.</b> رتبه‌ها از Search Console می‌آیند: میانگین ۲۸ روز و با حدود سه روز تأخیر. ترکر «کلمه‌ی کلیدی» (چک روزانه‌ی رتبه) متوقف است، چون سرویس گوگلی که به آن وابسته بود برای کل وب بسته شد.</li>
        <li><b>حجم جست‌وجو را نمی‌دانیم.</b> نمی‌دانیم یک عبارت ماهی چند بار جست‌وجو می‌شود. پیشنهاد هوش مصنوعی و «شکاف رقبا» حدس‌اند و باید قضاوت انسان بگذرد.</li>
        <li><b>Search Console فقط چیزی را نشان می‌دهد که سایت قبلاً برایش دیده شده.</b> موضوع کاملاً تازه در آن نیست؛ برای همین پیشنهاد هوش مصنوعی و جست‌وجوی داخل سایت وجود دارند.</li>
        <li><b>بک‌لینک دیده نمی‌شود</b> (فقط یادآوری برای بررسی دستی می‌دهد).</li>
        <li><b>پلاگین فقط می‌خواند.</b> هر اصلاح را خودتان در وردپرس انجام می‌دهید. سازنده‌های صفحه به‌جز Elementor تجزیه نمی‌شوند.</li>
        <li><b>سامانه مقاله نمی‌نویسد.</b> بریف می‌دهد و نویسنده می‌نویسد.</li>
        <li><b>پیشنهاد جست‌وجوی گوگل (Autocomplete) رسمی نیست</b> و ممکن است گوگل مسدودش کند؛ آن بخش بریف آن‌وقت خالی می‌ماند.</li>
        <li><b>آستانه‌ها از پیش تعیین شده‌اند</b> (مثل ۱۰۰ ایمپرشن یا ۲۰٪ افت)، نه تنظیم‌شده با داده‌ی سایت شما. اگر فرصتی بی‌ربط بود «رد کردن» را بزنید.</li>
      </ul>
      <p><b>چک‌لیست: یک سایت کاملاً راه‌اندازی شده است اگر…</b></p>
      <ol class="steps">
        <li><b>۱.</b> در فهرست پروژه‌ها، «اتصال Search Console» سبز (متصل) و «آخرین همگام‌سازی» امروز یا دیروز است.</li>
        <li><b>۲.</b> «خدمات و محصولات واقعی» پروژه پر و دقیق است.</li>
        <li><b>۳.</b> در «کلمات کلیدی» ردیف‌هایی با رتبه، کلیک و (پس از طبقه‌بندی) Intent و خوشه می‌بینید.</li>
        <li><b>۴.</b> ستون «وردپرس» در فهرست پروژه‌ها «متصل» است و در «محتوای سایت (وردپرس)» صفحه‌ها دیده می‌شوند.</li>
        <li><b>۵.</b> در برد «وضعیت سایت‌های وردپرسی» سایت «سالم» است.</li>
        <li><b>۶.</b> «خلأ پوشش محتوا» برای این پروژه جدول پر می‌کند (یا پیام روشنی می‌دهد).</li>
        <li><b>۷.</b> اگر Analytics را وصل کرده‌اید، ستون «نشست (GA)» در «محتوای سایت» عدد دارد.</li>
        <li><b>۸.</b> «تنظیمات هشدار سئو» روشن است و پیام آزمایشی تلگرام رسیده است.</li>
        <li><b>۹.</b> یک ممیزی فنی «تکمیل‌شده» دارید.</li>
        <li><b>۱۰.</b> حداقل یک آیتم در تقویم محتوا با مسئول و مهلت دارید.</li>
      </ol>
    </div>
  </section>

  <!-- ============ 15 — CUSTOMER WEB APP ============ -->
  <section class="chapter" id="ch-15">
    <div class="chapter-head">
      <div class="chapter-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="2.5" width="10" height="19" rx="2.5"/><path d="M11 18.5h2"/></svg>
      </div>
      <div>
        <span class="chapter-num">فصل ۱۵</span>
        <h2>وب‌اپ مشتریان (گنجنامه)</h2>
      </div>
    </div>
    <div class="chapter-body">
      <p class="lede">
        اپلیکیشن نصب‌شدنی (PWA) برای مشتریان در آدرس <b>/app</b>، با هویت جدا از پنل کارمندان: صفحه‌ی اصلی بدون ورود دیده می‌شود
        و محتوایش را خودتان از پنل می‌چینید؛ حساب مشتری برای رزرو، کیف پول، بلیت‌ها، پشتیبانی و تریپ‌پلنر است.
        نشست مشتری هیچ‌وقت به پنل کارمندان دسترسی نمی‌دهد و برعکس.
      </p>
      <h3>آنچه مدیر از پنل تنظیم می‌کند (گروه «وب‌اپ مشتریان»)</h3>
      <div class="feature-grid">
        <div class="feature-card"><div class="name">بنرهای تبلیغاتی</div><div class="desc">بنرهای صفحه‌ی اصلی اپ.</div></div>
        <div class="feature-card"><div class="name">بخش‌های صفحه‌ی اپ</div><div class="desc">چینش بخش‌ها و آیتم‌های صفحه‌ی اصلی، بدون نیاز به دیپلوی.</div></div>
        <div class="feature-card"><div class="name">منوی پایین اپ</div><div class="desc">برای هر گزینه‌ی منو: عنوان نمایشی، آیکون، ترتیب و نمایش/عدم نمایش.</div></div>
        <div class="feature-card"><div class="name">روش‌های ورود</div><div class="desc">سه کلید مستقل: کد پیامکی (OTP)، رمز عبور (با موبایل یا ایمیل) و گوگل.</div></div>
        <div class="feature-card"><div class="name">ورود با گوگل</div><div class="desc">Client ID و Client Secret را وارد کنید و «آدرس بازگشت» نمایش‌داده‌شده را دقیقاً در Google Cloud Console زیر Authorized redirect URIs ثبت کنید. کلید گوگل در «روش‌های ورود» هم باید روشن باشد.</div></div>
        <div class="feature-card"><div class="name">دامنه اختصاصی</div><div class="desc">نام دامنه (مثل app.example.com، بدون https و اسلش انتهایی) را بدهید و با «بررسی اتصال DNS» تأیید کنید؛ تا تأیید نشود، چیزی روی دامنه نشان داده نمی‌شود.</div></div>
      </div>
      <h3>آنچه مشتری می‌بیند</h3>
      <div class="tbl"><table class="ref">
        <thead><tr><th>بخش</th><th>کاربرد</th></tr></thead>
        <tbody>
          <tr><td>ورود و ثبت‌نام</td><td>با موبایل و کد پیامکی، با رمز عبور، یا با گوگل — هرکدام که فعال کرده باشید. شماره موبایل با هر املایی (۰۹۱۲…، ‎+98912…، ارقام فارسی) به همان مشتریِ قبلی وصل می‌شود و مشتری تکراری نمی‌سازد.</td></tr>
          <tr><td>رزروها</td><td>مشاهده‌ی رزروها و ثبت رزرو آنلاین.</td></tr>
          <tr><td>کیف پول</td><td>موجودی و گردش حساب؛ شارژ آنلاین از طریق درگاه پرداخت (وقتی در «درگاه پرداخت آنلاین» فعال باشد).</td></tr>
          <tr><td>بلیت‌ها</td><td>بلیت‌های خریداری‌شده‌ی مشتری.</td></tr>
          <tr><td>پشتیبانی</td><td>ثبت تیکت، گفتگوی پاسخ‌ها و امتیازدهی؛ تیکت در پنل زیر «تیکت‌های پشتیبانی» دیده می‌شود.</td></tr>
          <tr><td>پروفایل</td><td>اطلاعات حساب و رضایت‌نامه‌های بازاریابی (پیامک، ایمیل…) که تاریخچه‌ی آن‌ها در پرونده‌ی مشتری در پنل نگهداری می‌شود.</td></tr>
          <tr><td>تریپ‌پلنر</td><td>ساخت برنامه‌ی سفر؛ فصل بعد.</td></tr>
        </tbody>
      </table></div>
      <div class="callout">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round"><path d="M12 2 4 6v6c0 5 3.5 8.4 8 10 4.5-1.6 8-5 8-10V6l-8-4Z"/><path d="M9 12l2 2 4-4"/></svg>
        <div>
          <span class="label">مشتری در پنل</span>
          <p>هر مشتری یک پرونده در «مهمانان» دارد با تب‌های معاملات، تیکت‌ها، بازخوردها، گردش کیف پول، رضایت‌نامه‌ها و مکالمات؛ از همان صفحه می‌توانید ایمیل یا پیامک بفرستید، فعالیت CRM ثبت کنید یا پرونده را با پرونده‌ی دیگری ادغام کنید.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ 16 — TRIP PLANNER ============ -->
  <section class="chapter" id="ch-16">
    <div class="chapter-head">
      <div class="chapter-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4 3 6.5v13L9 17l6 3 6-2.5v-13L15 7 9 4Z"/><path d="M9 4v13M15 7v13"/></svg>
      </div>
      <div>
        <span class="chapter-num">فصل ۱۶</span>
        <h2>تریپ‌پلنر</h2>
      </div>
    </div>
    <div class="chapter-body">
      <p class="lede">
        مهمان می‌گوید چند روز در همدان است و دستیار هوشمند از میان «جاذبه‌های گردشگری» که شما ثبت کرده‌اید، برنامه‌ای روزبه‌روز
        می‌سازد که با ساعت کاری، روز تعطیل، زمان رفت‌وآمد و سرعت سفر جور باشد. هوش مصنوعی پیشنهاد می‌دهد، ولی خروجی همیشه با
        قواعد سامانه اصلاح می‌شود؛ اگر هوش مصنوعی در دسترس نباشد، برنامه‌ریز قانون‌محور جایش را می‌گیرد.
      </p>
      <div class="feature-grid">
        <div class="feature-card"><div class="name">جاذبه‌های گردشگری</div><div class="desc">دسته، مختصات (برای فعال‌کردن لازم است؛ تا خالی است جاذبه پیش‌نویس می‌ماند)، مدت متوسط بازدید، ساعت باز و بسته، <b>ساعت ویژه‌ی بعضی روزها</b>، روزهای تعطیل هفتگی، تعطیلی موقت بین دو تاریخ، هزینه‌ی ورودی، تلفن، مناسب کودک/سالمند، پارکینگ، بهترین زمان بازدید، محصول بلیت مرتبط، وزن ترجیح و یادداشت مدیر که مستقیم به دستیار گفته می‌شود.</div></div>
        <div class="feature-card"><div class="name">پیشنهاد جاذبه با هوش مصنوعی</div><div class="desc">در فهرست جاذبه‌ها، با یک تمرکز اختیاری و تعداد دلخواه، پیشنهاد جاذبه‌ی تازه بگیرید؛ پیش‌نمایش را ویرایش کنید و فقط مواردی را که تیک زده‌اید ثبت کنید.</div></div>
        <div class="feature-card"><div class="name">تنظیمات دستیار سفر</div><div class="desc">روشن/خاموش بودن تریپ‌پلنر، سقف برنامه‌ی جدید برای هر مهمان در شبانه‌روز (هر ساخت یک هزینه‌ی هوش مصنوعی است)، ساخت وظیفه‌ی پیگیری برای مهمانی که برنامه ساخته ولی رزرو ندارد، پیامک یادآوری یک روز قبل از سفر، و یادداشت عمومی برای دستیار (مثلاً «این هفته جشنواره‌ی گل است»).</div></div>
        <div class="feature-card"><div class="name">گزارش تریپ‌پلنر</div><div class="desc">تعداد برنامه‌ها، میانگین طول سفر، امتیاز مهمانان، سهم برنامه‌هایی که با برنامه‌ریز قانون‌محور ساخته شدند، تبدیل به رزرو، جاذبه‌های پرطرفدار و جاذبه‌هایی که هیچ‌وقت انتخاب نشدند.</div></div>
      </div>
      <h3>از دید مهمان</h3>
      <div class="tbl"><table class="ref">
        <thead><tr><th>مرحله</th><th>چه می‌شود</th></tr></thead>
        <tbody>
          <tr><td>درخواست</td><td>تعداد روز (۱ تا ۷)، تاریخ شروع، علایق، ریتم سفر (آرام، متعادل، فشرده)، نوع گروه، داشتن خودرو و سطح بودجه.</td></tr>
          <tr><td>ساخت در صف</td><td>درخواست ثبت می‌شود و صفحه‌ی «برنامه شما در حال ساخت است» خودکار تازه می‌شود؛ مهمان می‌تواند بیرون برود و بعداً از «برنامه‌های قبلی من» برگردد. ساخت از صف کرون سرور شروع می‌شود، پس ممکن است تا حدود یک دقیقه بعد شروع شود. اگر ساخت شکست بخورد، دلیلش به فارسی نمایش داده می‌شود و می‌توان دوباره امتحان کرد.</td></tr>
          <tr><td>ویرایش</td><td>افزودن یا حذف جاذبه، جابه‌جایی ترتیب، و «بازسازی این روز».</td></tr>
          <tr><td>خروجی</td><td>چاپ یا ذخیره PDF، افزودن به تقویم (فایل ‎.ics)، لینک اشتراک فقط‌خواندنی (قابل قطع)، و امتیاز و بازخورد.</td></tr>
        </tbody>
      </table></div>
      <div class="callout">
        <svg viewBox="0 0 24 24" fill="none" stroke-width="1.7" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
        <div>
          <span class="label">ساعت‌ها همیشه رعایت می‌شوند</span>
          <p>اگر جاذبه‌ای جمعه‌ها دیرتر باز شود، در «ساعت ویژه‌ی بعضی روزها» همان روز و ساعت را بدهید؛ بازدید آن جاذبه در جمعه از همان ساعت شروع می‌شود و بقیه‌ی روزها ساعت معمول می‌ماند. اگر مهمان همان درخواست را در ۱۰ دقیقه‌ی بعد دوباره بفرستد، همان برنامه‌ی قبلی نشان داده می‌شود و درخواست تکراری ساخته نمی‌شود.</p>
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
