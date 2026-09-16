---
paths:
  - '**/*'
---

# General

## Self-host all fonts/libraries/assets - never link a third-party CDN
This app is hosted for users in Iran, where CDNs (Google Fonts, Bunny Fonts, jsDelivr, unpkg, etc.) can be slow or filtered. Never reference an external CDN URL in a `<link>`/`<script>` tag or a Filament font provider default.

- Fonts: use Vite's font-bundling (see `vite.config.js`'s `fonts: [google(...)]` and the `@fonts` Blade directive / `Illuminate\Foundation\Vite::fonts()`), which downloads once at build time and serves from this app's own `public/build`. For Filament panels needing a custom font, use `App\Filament\Support\LocalViteFontProvider` instead of the default Bunny/Google font providers.
- JS/CSS libraries: add as an npm dependency (`package.json`) pinned to an exact version, then either bundle via Vite or - for old-style libraries that expect a plain global `<script>` tag (e.g. Leaflet, qrcodejs) - copy the built dist files from `node_modules` into `public/vendor/<library>/` and reference them with `asset('vendor/...')`. See `resources/views/filament/components/leaflet-assets.blade.php` for the pattern.
- Live third-party data services (e.g. OpenStreetMap map tiles) are not libraries and are out of scope for this rule - self-hosting those needs separate infrastructure (a tile server), not a simple local copy.
