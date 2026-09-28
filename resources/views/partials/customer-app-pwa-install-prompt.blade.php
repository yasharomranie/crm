{{--
    Same "install this app" nudge pattern as partials/pwa-install-prompt.blade.php
    (see its own doc comment for the full reasoning - 5s delay, iOS vs
    Android variants, 7-day snooze), reskinned for the گنجنامه customer app
    and using its own localStorage keys so dismissing/installing one app
    never affects the other's prompt.
--}}
<div id="ganjnameh-install-banner" hidden style="position:fixed; inset-inline:0; bottom:0; z-index:1000; display:flex; justify-content:center; padding:0 .75rem calc(.75rem + env(safe-area-inset-bottom, 0px)); transform:translateY(120%); transition:transform .3s ease; pointer-events:none;">
    <div style="pointer-events:auto; width:100%; max-width:26rem; display:flex; align-items:center; gap:.75rem; background:#fff; border:1px solid #fed7aa; border-radius:1rem; box-shadow:0 10px 30px rgb(0 0 0 / .15); padding:.75rem .9rem; font-family:'Vazirmatn', Tahoma, Arial, sans-serif;">
        <img src="{{ asset('icons/customer-app-icon-192.png') }}" alt="" style="width:2.75rem; height:2.75rem; border-radius:.75rem; flex-shrink:0;">

        <div style="flex:1; min-width:0;">
            <p data-variant="android" hidden style="margin:0 0 .35rem; font-size:.85rem; font-weight:600; color:#1c1917;">
                اپ گنجنامه رو نصب کن، مثل یه اپ مستقل بازش کن.
            </p>
            <p data-variant="ios" hidden style="margin:0 0 .35rem; font-size:.8rem; line-height:1.6; color:#1c1917;">
                برای نصب: دکمهٔ اشتراک‌گذاری <span aria-hidden="true">⬆️</span> رو بزنید، بعد «افزودن به صفحه اصلی» رو انتخاب کنید.
            </p>

            <div style="display:flex; gap:.5rem;">
                <button type="button" data-action="install" data-variant="android" hidden style="border:none; background:#f97316; color:#fff; font-family:inherit; font-size:.8rem; font-weight:600; padding:.4rem .9rem; border-radius:.5rem; cursor:pointer;">
                    نصب
                </button>
                <button type="button" data-action="dismiss" style="border:1px solid #e7e5e4; background:#fff; color:#57534e; font-family:inherit; font-size:.8rem; padding:.4rem .9rem; border-radius:.5rem; cursor:pointer;">
                    بعداً
                </button>
            </div>
        </div>

        <button type="button" data-action="dismiss" aria-label="بستن" style="flex-shrink:0; align-self:flex-start; border:none; background:transparent; color:#a8a29e; font-size:1.1rem; line-height:1; cursor:pointer; padding:.25rem;">
            ×
        </button>
    </div>
</div>

<script>
    (function () {
        var STORAGE_DISMISSED = 'ganjnameh-app-install-dismissed-until';
        var STORAGE_INSTALLED = 'ganjnameh-app-installed';
        var SNOOZE_MS = 7 * 24 * 60 * 60 * 1000;

        function isStandalone() {
            return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
        }

        function isMobile() {
            return window.matchMedia('(max-width: 820px)').matches;
        }

        function dismissedRecently() {
            var until = Number(localStorage.getItem(STORAGE_DISMISSED) || 0);

            return Date.now() < until;
        }

        var banner = document.getElementById('ganjnameh-install-banner');

        if (!banner || isStandalone() || !isMobile() || localStorage.getItem(STORAGE_INSTALLED) || dismissedRecently()) {
            return;
        }

        var isIos = /iphone|ipad|ipod/i.test(navigator.userAgent) && !window.MSStream;
        var variant = isIos ? 'ios' : 'android';
        var deferredPrompt = null;
        var timerElapsed = false;

        function showBanner() {
            if (banner.classList.contains('is-visible')) {
                return;
            }

            banner.querySelectorAll('[data-variant]').forEach(function (el) {
                el.hidden = el.dataset.variant !== variant;
            });

            banner.hidden = false;
            requestAnimationFrame(function () {
                banner.style.transform = 'translateY(0)';
                banner.classList.add('is-visible');
            });
        }

        function hideBanner() {
            banner.style.transform = 'translateY(120%)';
            banner.classList.remove('is-visible');
        }

        function dismiss() {
            localStorage.setItem(STORAGE_DISMISSED, String(Date.now() + SNOOZE_MS));
            hideBanner();
        }

        window.addEventListener('beforeinstallprompt', function (event) {
            event.preventDefault();
            deferredPrompt = event;

            if (timerElapsed) {
                showBanner();
            }
        });

        window.addEventListener('appinstalled', function () {
            localStorage.setItem(STORAGE_INSTALLED, '1');
            hideBanner();
        });

        setTimeout(function () {
            timerElapsed = true;

            if (isIos || deferredPrompt) {
                showBanner();
            }
        }, 5000);

        banner.querySelectorAll('[data-action="dismiss"]').forEach(function (button) {
            button.addEventListener('click', dismiss);
        });

        var installButton = banner.querySelector('[data-action="install"]');
        installButton.addEventListener('click', function () {
            if (!deferredPrompt) {
                return;
            }

            deferredPrompt.prompt();
            deferredPrompt.userChoice.then(function (choice) {
                if (choice.outcome === 'accepted') {
                    localStorage.setItem(STORAGE_INSTALLED, '1');
                }
                deferredPrompt = null;
            });
            hideBanner();
        });
    })();
</script>
