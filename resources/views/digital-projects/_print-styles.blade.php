<style>
    :root { color-scheme: light; --ink: #1c1917; --ink-soft: #57534e; --ink-faint: #a8a29e; --line: #e7e5e4; --gold: #9c6b1f; --paper: #f4f4f3; }
    * { box-sizing: border-box; }
    body { margin: 0; padding: 2rem 1.25rem; background: var(--paper); font-family: 'Vazirmatn', Tahoma, 'B Nazanin', Arial, sans-serif; color: var(--ink); line-height: 1.8; }
    .toolbar { max-width: 56rem; margin: 0 auto 1rem; display: flex; justify-content: flex-end; }
    .toolbar button { font-family: inherit; font-size: .875rem; font-weight: 500; border-radius: .5rem; padding: .55rem 1.1rem; border: 1px solid var(--gold); background: var(--gold); color: #fff; cursor: pointer; }
    .sheet { max-width: 56rem; margin: 0 auto; background: #fff; border-radius: .75rem; box-shadow: 0 1px 3px rgb(0 0 0 / .1); padding: 2.5rem; }
    .letterhead { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; padding-bottom: 1.25rem; border-bottom: 2px solid var(--ink); margin-bottom: 1.5rem; }
    .letterhead .app-name { font-size: 1.05rem; font-weight: 700; margin: 0 0 .3rem; color: var(--ink-soft); }
    h1.report-title { font-size: 1.25rem; font-weight: 800; margin: 0; }
    .letterhead .meta { flex-shrink: 0; text-align: left; font-size: .75rem; color: var(--ink-faint); line-height: 1.8; white-space: nowrap; }
    h2 { font-size: 1rem; margin: 1.75rem 0 .5rem; padding-bottom: .3rem; border-bottom: 1px solid var(--line); }
    h3 { font-size: .95rem; margin: 0 0 .25rem; }
    table { width: 100%; border-collapse: collapse; font-size: .85rem; }
    th, td { border-bottom: 1px solid var(--line); padding: .5rem; text-align: right; vertical-align: top; }
    th { font-size: .75rem; font-weight: 700; color: var(--ink-soft); background: #fafaf9; white-space: nowrap; }
    .muted { color: var(--ink-faint); }
    .late { color: #b91c1c; font-weight: 700; }
    .project { break-inside: avoid; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px dashed var(--line); }
    .tag { display: inline-block; font-size: .7rem; padding: 0 .5rem; border-radius: 999px; border: 1px solid var(--line); color: var(--ink-soft); }
    ul.lines { margin: .25rem 0; padding-inline-start: 1.25rem; font-size: .88rem; }
    footer.doc-footer { margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--line); font-size: .7rem; color: var(--ink-faint); text-align: center; }
    @media (max-width: 640px) { .sheet { padding: 1.5rem 1.25rem; overflow-x: auto; } .letterhead { flex-direction: column; } .letterhead .meta { text-align: right; } }
    @media print { body { background: #fff; padding: 0; } .toolbar { display: none; } .sheet { box-shadow: none; border-radius: 0; padding: 0; max-width: none; } }
</style>
