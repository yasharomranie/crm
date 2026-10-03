{{--
    One local include for Chart.js, shared by every dashboard-style chart
    (line/bar/doughnut) - see resources/views/filament/components/d3-assets
    for the same reasoning: this app is hosted for users in Iran, where a
    CDN can be slow or filtered, so a self-hosted copy means a chart never
    depends on a third-party host being reachable. D3 itself stays for
    the one custom visualization that needed it (the pillar-cluster
    radial tree); Chart.js is for everything else.

    Vendored under public/vendor/chartjs (chart.umd.min.js) instead of a
    CDN - an exact, unmodified copy of npm's chart.js@4 UMD build (see
    package.json's "chart.js" dependency - that's the version to bump if
    this ever needs updating, then re-copy from
    node_modules/chart.js/dist/chart.umd.min.js into
    public/vendor/chartjs/chart.umd.min.js).
--}}
@once
    <script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}"></script>
@endonce
