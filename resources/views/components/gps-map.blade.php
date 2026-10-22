@props(['lat', 'lng', 'height' => '12rem'])

{{--
    Plain inline styles here instead of Tailwind utility classes: this
    component is used both inside the admin panel (which has Tailwind
    compiled) and on the standalone print/PDF page (which doesn't load that
    build at all, only its own inline <style> block) - inline styles render
    correctly in both places instead of silently doing nothing on the
    second one.
--}}
@include('filament.components.leaflet-assets')

@php $mapId = 'gps-map-'.\Illuminate\Support\Str::random(10); @endphp

<div {{ $attributes }}>
    <div
        id="{{ $mapId }}"
        style="height: {{ $height }}; width: 100%; border-radius: .5rem; border: 1px solid #e7e5e4; overflow: hidden;"
    ></div>
    <a
        href="https://www.google.com/maps?q={{ $lat }},{{ $lng }}"
        target="_blank"
        rel="noopener"
        style="display: inline-block; margin-top: .35rem; font-size: .75rem; color: #9c6b1f; text-decoration: none;"
    >
        باز کردن در Google Maps ({{ \Illuminate\Support\Number::toPersian($lat) }}, {{ \Illuminate\Support\Number::toPersian($lng) }})
    </a>
</div>

<script>
    (function () {
        if (! window.L) {
            return;
        }

        var map = L.map('{{ $mapId }}', { attributionControl: true }).setView([{{ $lat }}, {{ $lng }}], 16);

        window.crmTileLayer(map, { maxZoom: 19 });

        L.marker([{{ $lat }}, {{ $lng }}]).addTo(map);
    })();
</script>
