{{--
    Push notification opt-in - admin panel only (there's no per-user
    subscription to speak of on the public landing page). Triggered by a
    user-menu item (see AdminPanelProvider's ->userMenuItems()) rather than
    firing automatically: browsers require a real user gesture before the
    permission prompt, and asking for it unprompted the moment someone logs
    in is exactly the kind of dark pattern that gets a site's notifications
    ignored/blocked forever. One menu click does the right thing either way
    - subscribes if not already, unsubscribes if it is - since Filament's
    server-rendered menu item can't reactively show two different
    labels for a client-side-only state.
--}}
@if (auth()->user())
    <script>
        (function () {
            var VAPID_PUBLIC_KEY = @js(config('webpush.vapid.public_key'));

            function urlBase64ToUint8Array(base64String) {
                var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
                var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
                var rawData = window.atob(base64);
                var outputArray = new Uint8Array(rawData.length);
                for (var i = 0; i < rawData.length; ++i) {
                    outputArray[i] = rawData.charCodeAt(i);
                }
                return outputArray;
            }

            function csrfToken() {
                var meta = document.querySelector('meta[name="csrf-token"]');
                return meta ? meta.getAttribute('content') : '';
            }

            function postSubscription(subscription) {
                return fetch('{{ route('push-subscriptions.store') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                    body: JSON.stringify(subscription.toJSON()),
                });
            }

            function deleteSubscription(endpoint) {
                return fetch('{{ route('push-subscriptions.destroy') }}', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                    body: JSON.stringify({ endpoint: endpoint }),
                });
            }

            window.farsiwpTogglePush = async function () {
                if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
                    window.alert('مرورگر شما از اعلان‌های فوری پشتیبانی نمی‌کند.');
                    return;
                }

                var registration = await navigator.serviceWorker.ready;
                var existing = await registration.pushManager.getSubscription();

                if (existing) {
                    await deleteSubscription(existing.endpoint);
                    await existing.unsubscribe();
                    window.alert('اعلان‌های فوری غیرفعال شد.');
                    return;
                }

                var permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    window.alert('اجازهٔ نمایش اعلان داده نشد.');
                    return;
                }

                var subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY),
                });

                await postSubscription(subscription);
                registration.showNotification('FarsiWP', {
                    body: 'اعلان‌های فوری فعال شد.',
                    icon: '/icons/icon-192.png',
                    dir: 'rtl',
                    lang: 'fa',
                });
            };
        })();
    </script>
@endif
