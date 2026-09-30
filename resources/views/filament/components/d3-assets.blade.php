{{--
    One local include for D3.js, shared by every visualization that needs
    it (currently only the pillar-cluster radial tree). @once dedupes this
    even across multiple @include()s in the same request.

    Vendored under public/vendor/d3 (d3.min.js) instead of a CDN: this app
    is hosted for users in Iran, where a CDN (jsdelivr, unpkg, ...) can be
    slow or filtered - a self-hosted copy means the chart never depends on
    a third-party host being reachable. The file is an exact, unmodified
    copy of npm's d3@7 UMD build (see package.json's "d3" dependency -
    that's the version to bump if this ever needs updating, then re-copy
    from node_modules/d3/dist/d3.min.js into public/vendor/d3/d3.min.js).
--}}
@once
    <script src="{{ asset('vendor/d3/d3.min.js') }}"></script>
@endonce
