{{-- Registers the service worker (public/sw.js) - see its own top comment
    for what it does and, just as importantly, what it deliberately leaves
    untouched - and shows a small "new version" banner when a deploy has
    shipped a new sw.js while a tab is already open on the old one. Without
    this, a tab left open across a deploy would keep serving whatever
    static assets it cached before, silently, for as long as it stays
    open - this makes that visible and lets the user pick when to reload
    into the new version, instead of it happening (or not) invisibly. --}}
<div id="pwa-update-banner" hidden style="position:fixed; inset-inline:0; top:0; z-index:1001; display:flex; justify-content:center; padding:.75rem .75rem 0 calc(.75rem + env(safe-area-inset-top, 0px)); transform:translateY(-120%); transition:transform .3s ease; pointer-events:none;">
    <div style="pointer-events:auto; display:flex; align-items:center; gap:.75rem; background:#1c1917; color:#fff; border-radius:.75rem; box-shadow:0 10px 30px rgb(0 0 0 / .25); padding:.6rem .5rem .6rem 1rem; font-family:'Vazirmatn', Tahoma, Arial, sans-serif; font-size:.82rem;">
        <span>نسخهٔ جدیدی از سامانه آماده‌ست.</span>
        <button type="button" id="pwa-update-reload" style="border:none; background:#f59e0b; color:#1c1917; font-family:inherit; font-size:.8rem; font-weight:700; padding:.35rem .8rem; border-radius:.5rem; cursor:pointer; white-space:nowrap;">
            به‌روزرسانی
        </button>
    </div>
</div>

<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('{{ asset('sw.js') }}').then(function (registration) {
                function announceUpdate() {
                    var banner = document.getElementById('pwa-update-banner');
                    if (!banner || banner.classList.contains('is-visible')) {
                        return;
                    }
                    banner.hidden = false;
                    requestAnimationFrame(function () {
                        banner.style.transform = 'translateY(0)';
                        banner.classList.add('is-visible');
                    });
                }

                // A worker already sitting in "waiting" (installed while no
                // tab was open to notice, or before this listener attached)
                // means an update is available right now.
                if (registration.waiting) {
                    announceUpdate();
                }

                registration.addEventListener('updatefound', function () {
                    var installingWorker = registration.installing;
                    if (!installingWorker) {
                        return;
                    }
                    installingWorker.addEventListener('statechange', function () {
                        if (installingWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            announceUpdate();
                        }
                    });
                });

                var reloadButton = document.getElementById('pwa-update-reload');
                if (reloadButton) {
                    reloadButton.addEventListener('click', function () {
                        if (registration.waiting) {
                            registration.waiting.postMessage('SKIP_WAITING');
                        }
                    });
                }
            }).catch(function () {});

            var refreshing = false;
            navigator.serviceWorker.addEventListener('controllerchange', function () {
                if (refreshing) {
                    return;
                }
                refreshing = true;
                window.location.reload();
            });
        });
    }
</script>
