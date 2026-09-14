{{--
    One CDN include for Leaflet + OpenStreetMap tiles, shared by every place
    a GPS field's value gets drawn as a map (the live form picker, the
    submission detail view, the print/PDF report). @once dedupes this even
    across multiple @include()s in the same request, so it's safe to pull
    in from more than one of those views at a time.

    No API key, no billing account: OpenStreetMap's tile server is free for
    normal, low-volume use like this. If a network policy blocks jsdelivr
    (as this dev sandbox's does), the map silently doesn't render and the
    "باز کردن در Google Maps" link next to it still works - same
    graceful-degradation approach already used for the QR code on printed
    forms.
--}}
@once
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"></script>
@endonce
