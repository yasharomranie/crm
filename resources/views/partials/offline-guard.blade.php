{{-- App-wide "you're offline" safety net (every panel page, not just the
    Gate Scanner's own dedicated offline mode) - for the ordinary case of a
    brief connectivity drop while filling out any form. Two things only:
    1) a persistent, non-blocking bar pinned to the bottom of the screen so
    the user always knows their connection is down without it getting in
    the way of what they're doing, and 2) the browser's own native
    "leave site?" confirmation if they try to close the tab, reload, or
    navigate away while offline - unsaved input in a Livewire form only
    exists in that tab's memory, so this is what actually stops it from
    being silently lost. Deliberately does nothing while online: no
    dialogs, no reminders, nothing to dismiss. --}}
<div
    id="offline-guard-bar"
    hidden
    style="position:fixed; inset-inline:0; bottom:0; z-index:1000; display:flex; justify-content:center; padding:0 .75rem calc(.75rem + env(safe-area-inset-bottom, 0px)); transform:translateY(120%); transition:transform .3s ease; pointer-events:none;"
>
    <div
        id="offline-guard-pill"
        style="pointer-events:auto; display:flex; align-items:center; gap:.6rem; background:#78350f; color:#fff; border-radius:.75rem; box-shadow:0 10px 30px rgb(0 0 0 / .25); padding:.55rem 1rem; font-family:'Vazirmatn', Tahoma, Arial, sans-serif; font-size:.82rem; max-width:calc(100vw - 1.5rem);"
    >
        <span style="width:.5rem; height:.5rem; border-radius:999px; background:#fbbf24; flex-shrink:0;"></span>
        <span id="offline-guard-text">اتصال اینترنت قطع شده. می‌تونید کارتون رو ادامه بدید، ولی تا وصل‌شدن دوباره، اطلاعات ذخیره نمی‌شه.</span>
    </div>
</div>

<script>
    (function () {
        var bar = document.getElementById('offline-guard-bar');
        var pill = document.getElementById('offline-guard-pill');
        var text = document.getElementById('offline-guard-text');
        var hideTimeout = null;

        function showBar() {
            clearTimeout(hideTimeout);
            bar.hidden = false;
            pill.style.background = '#78350f';
            text.textContent = 'اتصال اینترنت قطع شده. می‌تونید کارتون رو ادامه بدید، ولی تا وصل‌شدن دوباره، اطلاعات ذخیره نمی‌شه.';
            requestAnimationFrame(function () {
                bar.style.transform = 'translateY(0)';
            });
        }

        function showReconnectedThenHide() {
            pill.style.background = '#166534';
            text.textContent = 'دوباره آنلاین شدید.';
            bar.style.transform = 'translateY(0)';
            bar.hidden = false;

            hideTimeout = setTimeout(function () {
                bar.style.transform = 'translateY(120%)';
                setTimeout(function () { bar.hidden = true; }, 300);
            }, 2500);
        }

        function beforeUnloadHandler(event) {
            event.preventDefault();
            // Modern browsers show their own generic wording regardless of
            // this string, but returnValue still has to be set for the
            // prompt to appear at all.
            event.returnValue = '';
        }

        function handleOffline() {
            showBar();
            window.addEventListener('beforeunload', beforeUnloadHandler);
        }

        function handleOnline() {
            window.removeEventListener('beforeunload', beforeUnloadHandler);

            if (! bar.hidden) {
                showReconnectedThenHide();
            }
        }

        window.addEventListener('offline', handleOffline);
        window.addEventListener('online', handleOnline);

        if (! navigator.onLine) {
            handleOffline();
        }
    })();
</script>
