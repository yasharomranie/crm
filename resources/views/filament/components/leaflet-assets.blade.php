{{--
    One local include for Leaflet + OpenStreetMap tiles, shared by every
    place a GPS field's value gets drawn as a map (the live form picker, the
    submission detail view, the print/PDF report). @once dedupes this even
    across multiple @include()s in the same request, so it's safe to pull
    in from more than one of those views at a time.

    Vendored under public/vendor/leaflet (leaflet.js, leaflet.css, images/)
    instead of a CDN: this app is hosted for users in Iran, where a CDN
    (jsdelivr, unpkg, ...) can be slow or filtered - a self-hosted copy
    means the map never depends on a third-party host being reachable. The
    files are an exact, unmodified copy of npm's leaflet@1.9.4 (see
    package.json's "leaflet" dependency - that's the version to bump if
    this ever needs updating, then re-copy from
    node_modules/leaflet/dist/{leaflet.js,leaflet.css,images/*} into
    public/vendor/leaflet/).

    No API key, no billing account: OpenStreetMap's tile server is free for
    normal, low-volume use like this. The tile address is config('services.
    maps.tile_url') (MAP_TILE_URL) - it can point at this app's own
    /map-tiles route (a tile pack on the local disk). If the tile server is
    unreachable, public/vendor/leaflet/crm-tiles.js shows a notice and only
    the pictures are missing - the marker and the "باز کردن در Google Maps"
    link next to it still work.
--}}
@once
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}" />
    <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
    <script>window.crmMapTileUrl = @json(config('services.maps.tile_url'));</script>
    <script src="{{ asset('vendor/leaflet/crm-tiles.js') }}"></script>
@endonce
