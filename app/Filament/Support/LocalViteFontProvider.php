<?php

namespace App\Filament\Support;

use Filament\FontProviders\Contracts\FontProvider;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;

/**
 * Filament's own font providers (BunnyFontProvider, GoogleFontProvider) all
 * fetch the font from a third-party CDN at runtime, on every page load. This
 * app is hosted for users in Iran, where those CDNs can be slow or
 * filtered, so the font needs to come from this server instead - which is
 * exactly what Vite's own font-bundling feature already does for the
 * public landing page (see vite.config.js's `fonts: [google('Vazirmatn',
 * ...)]`, resolved through Illuminate\Foundation\Vite::fonts() /
 * the `@fonts` Blade directive): it downloads the font once at build time
 * and serves it from this app's own `public/build`, no CDN involved at
 * request time. This provider is the bridge that lets Filament's ->font()
 * hook use that same pre-built asset instead of reaching for Bunny/Google.
 */
class LocalViteFontProvider implements FontProvider
{
    public function getHtml(string $family, ?string $url = null): Htmlable
    {
        $alias = Str::of($family)->lower()->replaceMatches('/[^a-z0-9]+/', '-')->trim('-')->toString();

        return Vite::fonts($alias);
    }
}
