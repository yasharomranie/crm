<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves map tiles from this app's own disk (map-tiles/{z}/{x}/{y}.png on the
 * local disk) so maps keep working when the international internet is cut. The
 * tiles are an ordinary tile pack you put there yourself; a tile that is not
 * there is a plain 404 and the map simply shows no picture for it. Only
 * numbers are accepted, so no path can leave the folder.
 */
class MapTileController extends Controller
{
    public function show(int $z, int $x, int $y): BinaryFileResponse
    {
        abort_unless($z >= 0 && $z <= 22 && $x >= 0 && $y >= 0 && $x < 2 ** $z && $y < 2 ** $z, 404);

        $relative = "map-tiles/{$z}/{$x}/{$y}.png";

        abort_unless(Storage::disk('local')->exists($relative), 404);

        return response()->file(Storage::disk('local')->path($relative), [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }
}
