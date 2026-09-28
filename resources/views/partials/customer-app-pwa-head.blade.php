{{--
    PWA head tags for the گنجنامه CUSTOMER app only - its own manifest and
    theme color, deliberately separate from partials/pwa-head.blade.php
    (the staff admin panel's shared identity). Installing from /app never
    offers to install the admin panel, and vice versa.
--}}
<link rel="manifest" href="{{ asset('customer-app.webmanifest') }}">
<link rel="icon" href="{{ asset('icons/customer-app-icon-192.png') }}">
<meta name="theme-color" content="#f97316">
<link rel="apple-touch-icon" href="{{ asset('icons/customer-app-apple-touch-icon.png') }}">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="گنجنامه">
