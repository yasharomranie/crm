{{--
    Shared PWA head tags - included both in the public landing page and,
    via a panel-wide render hook, in every admin panel page. One manifest
    covers the whole app (scope "/"), so installing from either place opens
    the same installed app.
--}}
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<meta name="theme-color" content="#f59e0b">
<link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="FarsiWP">
