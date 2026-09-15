{{--
    The panel's brand mark: the same amber mascot used everywhere else
    (resources/views/livewire/ai-assistant-widget.blade.php,
    resources/views/welcome.blade.php) at a small, static topbar size,
    next to the FarsiWP wordmark and its English tagline. Rendered inline
    with plain style="" attributes rather than a class-based <style> block
    or Tailwind utilities - this partial gets embedded wherever Filament's
    own logo component is used (the login page and every panel topbar), so
    it needs to be fully self-contained regardless of which CSS bundle
    happens to be loaded on that particular page.
--}}
<div style="display:flex; align-items:center; gap:.5rem; direction:ltr;">
    <span style="position:relative; display:block; width:28px; height:28px; flex-shrink:0;" aria-hidden="true">
        <span style="position:absolute; top:-4px; inset-inline-start:1px; width:9px; height:9px; border-radius:50%; background:linear-gradient(155deg,#fde68a,#f59e0b);"></span>
        <span style="position:absolute; top:-4px; inset-inline-end:1px; width:9px; height:9px; border-radius:50%; background:linear-gradient(155deg,#fde68a,#f59e0b);"></span>
        <span style="position:absolute; inset:0; border-radius:50%; background:linear-gradient(155deg,#fde68a 0%,#f59e0b 55%,#d97706 100%);"></span>
        <span style="position:absolute; top:10px; inset-inline-start:5px; width:4px; height:2.5px; border-radius:50%; background:rgb(255 255 255 / .35);"></span>
        <span style="position:absolute; top:10px; inset-inline-end:5px; width:4px; height:2.5px; border-radius:50%; background:rgb(255 255 255 / .35);"></span>
        <span style="position:absolute; top:10.5px; inset-inline-start:0; inset-inline-end:0; display:flex; justify-content:center; gap:5px;">
            <span style="width:3.5px; height:3.5px; border-radius:50%; background:#1c1917;"></span>
            <span style="width:3.5px; height:3.5px; border-radius:50%; background:#1c1917;"></span>
        </span>
        <span style="position:absolute; top:17px; inset-inline-start:0; inset-inline-end:0; margin-inline:auto; width:6px; height:3px; border-radius:0 0 6px 6px; background:#b45309; opacity:.55;"></span>
    </span>
    <span style="display:flex; flex-direction:column; line-height:1.2;">
        <span style="font-weight:800; font-size:.95rem; white-space:nowrap;">
            <span style="color:#57534e;">Farsi</span><span style="color:#d97706;">WP</span>
        </span>
        <span style="font-size:.5rem; color:#a8a29e; white-space:nowrap;">Fully Automated Relationship &amp; System Intelligence, Word Pipeline</span>
    </span>
</div>
