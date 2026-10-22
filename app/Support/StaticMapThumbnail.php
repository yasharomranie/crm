<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Renders a small, self-contained PNG snapshot of a GPS point - for
 * contexts that can't run the JS a live Leaflet map needs (the Word
 * export, and the merged "متن سند" print/preview shared with it - see
 * FormSubmission::mergedDocumentBody()). Composites real OpenStreetMap
 * tiles server-side with GD, the same tile source the in-app live map
 * already depends on (see .ai/rules/general.md's carve-out for map tiles
 * from the self-hosting rule), then caches the result on disk keyed by
 * the rounded coordinate so a given location is only ever fetched once -
 * both for speed and to stay a light, well-behaved consumer of OSM's
 * tile servers.
 */
class StaticMapThumbnail
{
    private const ZOOM = 16;

    private const TILE_SIZE = 256;

    private const WIDTH = 320;

    private const HEIGHT = 220;

    /**
     * A `data:image/png;base64,...` URI ready to drop straight into an
     * <img src="">, or null if the tiles couldn't be fetched (offline
     * server, OSM unreachable, etc.) - callers must fall back to a plain
     * text/link in that case, never break the page over a missing image.
     */
    public static function dataUri(float $lat, float $lng): ?string
    {
        $png = static::cachedPng($lat, $lng);

        return $png ? 'data:image/png;base64,'.base64_encode($png) : null;
    }

    private static function cachedPng(float $lat, float $lng): ?string
    {
        $disk = Storage::disk();
        $path = 'map-thumbnails/'.md5(round($lat, 5).','.round($lng, 5)).'.png';

        if ($disk->exists($path)) {
            return $disk->get($path);
        }

        $png = static::render($lat, $lng);

        if ($png !== null) {
            $disk->put($path, $png);
        }

        return $png;
    }

    private static function render(float $lat, float $lng): ?string
    {
        [$xTileFrac, $yTileFrac] = static::tileCoordinates($lat, $lng, self::ZOOM);
        $xTile = (int) floor($xTileFrac);
        $yTile = (int) floor($yTileFrac);

        // A fixed 3x3 grid around the point's own tile - simpler than
        // computing exactly which neighbours are needed, and guarantees
        // the marker (always inside the center tile) has a full tile's
        // width of padding on every side before the final crop below.
        $canvas = imagecreatetruecolor(3 * self::TILE_SIZE, 3 * self::TILE_SIZE);

        for ($dx = -1; $dx <= 1; $dx++) {
            for ($dy = -1; $dy <= 1; $dy++) {
                $tile = static::fetchTile($xTile + $dx, $yTile + $dy, self::ZOOM);

                if ($tile === null) {
                    imagedestroy($canvas);

                    return null;
                }

                imagecopy(
                    $canvas, $tile,
                    (1 + $dx) * self::TILE_SIZE, (1 + $dy) * self::TILE_SIZE,
                    0, 0, self::TILE_SIZE, self::TILE_SIZE,
                );
                imagedestroy($tile);
            }
        }

        $markerX = (int) round((1 + ($xTileFrac - $xTile)) * self::TILE_SIZE);
        $markerY = (int) round((1 + ($yTileFrac - $yTile)) * self::TILE_SIZE);
        static::drawMarker($canvas, $markerX, $markerY);

        $canvasSize = 3 * self::TILE_SIZE;
        $srcX = (int) max(0, min($markerX - self::WIDTH / 2, $canvasSize - self::WIDTH));
        $srcY = (int) max(0, min($markerY - self::HEIGHT / 2, $canvasSize - self::HEIGHT));

        $thumbnail = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagecopy($thumbnail, $canvas, 0, 0, $srcX, $srcY, self::WIDTH, self::HEIGHT);
        imagedestroy($canvas);

        ob_start();
        imagepng($thumbnail);
        $png = ob_get_clean();
        imagedestroy($thumbnail);

        return $png ?: null;
    }

    /**
     * Standard Slippy Map fractional tile coordinates (see the OSM wiki's
     * "Slippy map tilenames" page) - the fractional part is exactly the
     * point's own pixel offset within its tile, used below to place the
     * marker.
     *
     * @return array{0: float, 1: float}
     */
    private static function tileCoordinates(float $lat, float $lng, int $zoom): array
    {
        $n = 2 ** $zoom;
        $x = ($lng + 180) / 360 * $n;
        $latRad = deg2rad($lat);
        $y = (1 - asinh(tan($latRad)) / M_PI) / 2 * $n;

        return [$x, $y];
    }

    /**
     * A tile from the local tile pack (map-tiles/{z}/{x}/{y}.png on the local
     * disk) if there is one, otherwise from the configured tile server; null
     * when neither has it (no internet, no pack).
     *
     * @return \GdImage|null
     */
    private static function fetchTile(int $x, int $y, int $zoom)
    {
        $local = "map-tiles/{$zoom}/{$x}/{$y}.png";

        if (Storage::disk('local')->exists($local)) {
            $image = @imagecreatefromstring((string) Storage::disk('local')->get($local));

            return $image ?: null;
        }

        $template = (string) config('services.maps.tile_url');

        if (! str_starts_with($template, 'http')) {
            return null;
        }

        $url = str_replace(['{s}', '{z}', '{x}', '{y}'], [['a', 'b', 'c'][($x + $y) % 3], $zoom, $x, $y], $template);

        try {
            // A descriptive User-Agent is required by OSM's tile usage
            // policy for any non-browser client.
            $response = Http::withHeaders(['User-Agent' => 'FarsiWP-CRM/1.0 (form submission map thumbnails)'])
                ->connectTimeout(3)
                ->timeout(5)
                ->get($url);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $image = @imagecreatefromstring($response->body());

        return $image ?: null;
    }

    private static function drawMarker($canvas, int $x, int $y): void
    {
        $white = imagecolorallocate($canvas, 255, 255, 255);
        $red = imagecolorallocate($canvas, 220, 38, 38);

        imagefilledellipse($canvas, $x, $y, 20, 20, $white);
        imagefilledellipse($canvas, $x, $y, 13, 13, $red);
        imagefilledellipse($canvas, $x, $y, 5, 5, $white);
    }
}
