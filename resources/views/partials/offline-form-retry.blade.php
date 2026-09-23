{{-- App-wide "don't lose a submission that happened to go out right as the
    connection dropped" - a step beyond offline-guard.blade.php's leave-page
    warning. Every Livewire action (a Filament resource's "ثبت"/save
    button, a table action, anything) goes out over Livewire's own wire
    protocol as one HTTP request; when that request fails because there's
    genuinely no connection (Livewire's own hook reports this as a fixed
    status 503 with no content - see registerLegacyEventSupport in
    livewire.js - never a real server response), the *exact* request
    (url + headers + body Livewire already built) is queued in
    localStorage instead of being discarded, and replayed unmodified the
    moment the connection comes back. The server processes a replayed
    request exactly as if it had just arrived - this needs no per-resource
    code, so it covers every Filament form (Bookings, Deals, Form
    Submissions, Rooms, everywhere) the instant it's registered once, here,
    at the panel level.

    Every queued item carries a per-item X-Offline-Retry-Id header, checked
    server-side by EnsureIdempotentLivewireUpdate. Without it, a request
    the server already processed - but whose success this queue never got
    to record locally, because the whole flush is one page's worth of JS
    state and a refresh/unload can land at any point in it - gets replayed
    again and creates a second, duplicate record (a Filament create action
    isn't itself idempotent). The header makes any number of retries of
    the same queued item produce that side effect exactly once, no matter
    how the timing falls. --}}
<script>
    document.addEventListener('livewire:init', () => {
        const STORAGE_KEY = 'offline-pending-livewire-requests';

        function loadQueue() {
            try {
                return JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
            } catch (e) {
                return [];
            }
        }

        function saveQueue(queue) {
            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(queue));
            } catch (e) {
                // Storage full or unavailable (private browsing) - nothing
                // more we can do here, the request is simply lost like it
                // would have been without this whole mechanism.
            }
        }

        function notice(text, tone) {
            window.dispatchEvent(new CustomEvent('offline-form-notice', { detail: { text, tone } }));
        }

        function generateRetryId() {
            if (window.crypto && typeof window.crypto.randomUUID === 'function') {
                return window.crypto.randomUUID();
            }

            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
                var r = (Math.random() * 16) | 0;
                var v = c === 'x' ? r : (r & 0x3) | 0x8;
                return v.toString(16);
            });
        }

        Livewire.hook('request', ({ url, options, payload, respond, succeed, fail }) => {
            fail(({ status, content, preventDefault }) => {
                // A real server response (422 validation, 403, 500 with an
                // actual body, ...) must keep going through Livewire's own
                // handling untouched - only a genuine network failure,
                // which Livewire always reports as exactly this shape,
                // gets queued instead.
                if (status !== 503 || content !== null) {
                    return;
                }

                preventDefault();

                // options.signal is a real AbortSignal object - it doesn't
                // survive a JSON round-trip through localStorage (it comes
                // back as an inert plain object, not a signal), and a later
                // fetch() call given that dead object as `signal` throws
                // immediately instead of sending the request at all. This
                // replay has nothing to abort anyway, so it's dropped.
                const { signal, ...storableOptions } = options;

                storableOptions.headers = Object.assign({}, storableOptions.headers, {
                    'X-Offline-Retry-Id': generateRetryId(),
                });

                const queue = loadQueue();
                queue.push({ url, options: storableOptions, queuedAt: new Date().toISOString() });
                saveQueue(queue);

                notice('اتصال اینترنت قطع بود، این ثبت ذخیره شد و به‌محض وصل‌شدن خودکار ارسال می‌شه.', 'warning');
            });
        });

        let flushing = false;

        async function flushQueue() {
            if (flushing) {
                return;
            }

            const queue = loadQueue();

            if (queue.length === 0) {
                return;
            }

            flushing = true;
            let sentCount = 0;
            const remaining = [];

            for (const item of queue) {
                try {
                    const response = await fetch(item.url, item.options);

                    if (response.ok) {
                        sentCount++;
                    } else {
                        // A real error this time (e.g. the record was
                        // deleted by someone else in the meantime, or the
                        // session expired during the outage) - stop
                        // retrying it automatically, but don't lose it
                        // silently either.
                        remaining.push(item);
                    }
                } catch (e) {
                    // Still offline (or offline again already) - keep it
                    // queued for the next reconnect.
                    remaining.push(item);
                }

                // Persisted after every item, not just once at the end of
                // the loop: a queue with several items can take a while to
                // flush, and a refresh partway through must not see the
                // already-sent ones as still pending (the X-Offline-Retry-Id
                // header keeps a genuine resend harmless either way, but
                // there's no reason to rely on that when it's this cheap to
                // avoid).
                saveQueue([...remaining, ...queue.slice(queue.indexOf(item) + 1)]);
            }

            flushing = false;

            if (sentCount > 0) {
                notice(
                    sentCount === 1
                        ? 'یک ثبت ذخیره‌شده از زمان قطعی، حالا با موفقیت ارسال شد.'
                        : sentCount + ' ثبت ذخیره‌شده از زمان قطعی، حالا با موفقیت ارسال شدن.',
                    'success',
                );
            }
        }

        window.addEventListener('online', flushQueue);

        // A queued request can also be waiting from before this exact page
        // load (the tab was closed and reopened after reconnecting).
        if (navigator.onLine) {
            flushQueue();
        }
    });
</script>
