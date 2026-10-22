<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Map pictures from a tile pack kept on this server, so maps work when the
 * international internet is cut.
 */
class MapTileRouteTest extends TestCase
{
    public function test_a_tile_in_the_local_pack_is_served_as_a_cacheable_png(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('map-tiles/16/41212/26337.png', 'png-bytes');

        $response = $this->get('/map-tiles/16/41212/26337.png');

        $response->assertOk();
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('max-age', (string) $response->headers->get('Cache-Control'));
    }

    public function test_a_tile_that_is_not_in_the_pack_is_a_plain_404(): void
    {
        Storage::fake('local');

        $this->get('/map-tiles/16/1/1.png')->assertNotFound();
    }

    public function test_impossible_coordinates_and_paths_are_refused(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('map-tiles/1/0/0.png', 'x');

        $this->get('/map-tiles/1/5/0.png')->assertNotFound();
        $this->get('/map-tiles/30/0/0.png')->assertNotFound();
        $this->get('/map-tiles/1/..%2F..%2Fx/0.png')->assertNotFound();
        $this->get('/map-tiles/a/b/c.png')->assertNotFound();
    }

    public function test_the_pages_that_draw_maps_get_their_tile_address_from_the_config(): void
    {
        config(['services.maps.tile_url' => '/map-tiles/{z}/{x}/{y}.png']);

        $html = (string) $this->blade("@include('filament.components.leaflet-assets')");

        $this->assertStringContainsString('window.crmMapTileUrl = "\/map-tiles\/{z}\/{x}\/{y}.png"', $html);
        $this->assertStringContainsString('vendor/leaflet/crm-tiles.js', $html);
    }
}
