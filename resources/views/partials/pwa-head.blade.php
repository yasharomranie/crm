{{--
    Shared PWA head tags - included both in the public landing page and,
    via a panel-wide render hook, in every admin panel page. One manifest
    covers the whole app (scope "/"), so installing from either place opens
    the same installed app.
--}}
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<meta name="theme-color" content="#f59e0b" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#1c1917" media="(prefers-color-scheme: dark)">
<link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="FarsiWP">

{{--
    iOS splash screens: Android/Chrome builds one automatically from the
    manifest (name + background_color + a large icon, already set above -
    nothing more to do there), but iOS Safari only shows a splash screen
    for images explicitly listed here, one per device size. This list
    covers iPhone SE-class through the current Pro Max plus one common
    iPad size, portrait only. Missing a specific device just means no
    splash screen for it, not a broken one.
--}}
@php
    $iosSplashScreens = [
        ['device' => 750, 'height' => 1334, 'ratio' => 2],
        ['device' => 828, 'height' => 1792, 'ratio' => 2],
        ['device' => 1125, 'height' => 2436, 'ratio' => 3],
        ['device' => 1170, 'height' => 2532, 'ratio' => 3],
        ['device' => 1179, 'height' => 2556, 'ratio' => 3],
        ['device' => 1242, 'height' => 2688, 'ratio' => 3],
        ['device' => 1284, 'height' => 2778, 'ratio' => 3],
        ['device' => 1290, 'height' => 2796, 'ratio' => 3],
        ['device' => 1536, 'height' => 2048, 'ratio' => 2],
    ];
@endphp
@foreach ($iosSplashScreens as $screen)
    @php
        $cssWidth = $screen['device'] / $screen['ratio'];
        $cssHeight = $screen['height'] / $screen['ratio'];
    @endphp
    <link
        rel="apple-touch-startup-image"
        media="(device-width: {{ $cssWidth }}px) and (device-height: {{ $cssHeight }}px) and (-webkit-device-pixel-ratio: {{ $screen['ratio'] }}) and (orientation: portrait)"
        href="{{ asset("icons/splash-{$screen['device']}x{$screen['height']}.png") }}"
    >
@endforeach
