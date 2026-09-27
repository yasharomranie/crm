{{--
    Loads the qr-scanner library (nimiq/qr-scanner) for the Gate Scanner
    page's camera-based QR reading - see gate-scanner.blade.php.

    Vendored under public/vendor/qr-scanner (qr-scanner.umd.min.js,
    qr-scanner-worker.min.js) instead of a CDN, same reasoning as
    leaflet-assets.blade.php: this app is hosted for users in Iran, where a
    CDN can be slow or filtered. An exact, unmodified copy of npm's
    qr-scanner@1.4.2 (see package.json - that's the version to bump if this
    ever needs updating, then re-copy from
    node_modules/qr-scanner/{qr-scanner.umd.min.js,qr-scanner-worker.min.js}
    into public/vendor/qr-scanner/).

    The worker file does the actual frame decoding off the main thread, so
    the library needs its own URL to fetch it from - set once, here, before
    any page script constructs a QrScanner instance.
--}}
@once
    <script src="{{ asset('vendor/qr-scanner/qr-scanner.umd.min.js') }}"></script>
    <script>
        QrScanner.WORKER_PATH = '{{ asset('vendor/qr-scanner/qr-scanner-worker.min.js') }}';
    </script>
@endonce
