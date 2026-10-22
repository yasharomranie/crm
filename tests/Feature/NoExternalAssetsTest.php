<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The app is hosted for users in Iran: when the international internet is cut
 * it must still load completely. So no page may pull a script, style, font,
 * image or frame from an outside host (see .ai/rules/general.md). Links the
 * user clicks (Google Maps, WhatsApp) and XML namespaces are not assets.
 */
class NoExternalAssetsTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function sourceFiles(): array
    {
        $roots = [resource_path('views'), resource_path('js'), resource_path('css'), public_path()];
        $files = [];

        foreach ($roots as $root) {
            foreach (File::allFiles($root) as $file) {
                $path = $file->getPathname();

                if (preg_match('#/public/(vendor|build|js|css|fonts|images|icons|system-guide-assets)/#', $path)) {
                    continue;
                }

                if (preg_match('/\.(php|js|css|html|webmanifest)$/', $path)) {
                    $files[] = $path;
                }
            }
        }

        return $files;
    }

    public function test_no_page_loads_a_script_style_font_image_or_frame_from_an_outside_host(): void
    {
        $patterns = [
            '/<(script|link|img|iframe|source|video|audio|embed|object)\b[^>]*\b(src|href|data)\s*=\s*["\']\s*(https?:)?\/\/(?!localhost)/i',
            '/@import\s+(url\()?["\']?(https?:)?\/\//i',
            '/url\(\s*["\']?(https?:)?\/\/(?!localhost)/i',
            '/\bimport\s*\(?\s*["\']https?:\/\//i',
        ];
        $found = [];

        foreach ($this->sourceFiles() as $path) {
            $contents = File::get($path);

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $contents, $match)) {
                    $found[] = str_replace(base_path().'/', '', $path).' => '.trim($match[0]);
                }
            }
        }

        $this->assertSame([], $found, "Outside hosts must not serve assets:\n".implode("\n", $found));
    }

    public function test_no_known_cdn_or_font_host_appears_in_the_app_files(): void
    {
        $hosts = '(fonts\.googleapis\.com|fonts\.gstatic\.com|fonts\.bunny\.net|cdn\.jsdelivr\.net|cdnjs\.cloudflare\.com|unpkg\.com|code\.jquery\.com|stackpath\.bootstrapcdn\.com|maxcdn\.bootstrapcdn\.com|use\.fontawesome\.com|ajax\.googleapis\.com|www\.googletagmanager\.com|www\.google-analytics\.com|ui-avatars\.com|gravatar\.com|polyfill\.io)';
        $found = [];

        foreach ($this->sourceFiles() as $path) {
            $contents = File::get($path);

            // The self-hosting notes in comments name these hosts on purpose.
            $contents = preg_replace(['/\{\{--.*?--\}\}/s', '/\/\*.*?\*\//s', '/^\s*(\/\/|\*|#).*$/m'], '', $contents) ?? $contents;

            if (preg_match('/'.$hosts.'/i', $contents, $match)) {
                $found[] = str_replace(base_path().'/', '', $path).' => '.$match[0];
            }
        }

        $this->assertSame([], $found, "CDN hosts must not be used:\n".implode("\n", $found));
    }

    public function test_the_map_tiles_are_the_only_outside_picture_source_and_come_from_one_config_value(): void
    {
        $this->assertStringContainsString('tile.openstreetmap.org', (string) config('services.maps.tile_url'));

        foreach ($this->sourceFiles() as $path) {
            $this->assertStringNotContainsString('tile.openstreetmap.org', File::get($path), str_replace(base_path().'/', '', $path).' must use config(\'services.maps.tile_url\')');
        }
    }

    public function test_the_libraries_and_fonts_the_pages_need_are_inside_the_project(): void
    {
        foreach (['vendor/leaflet/leaflet.js', 'vendor/leaflet/crm-tiles.js', 'vendor/chartjs/chart.umd.min.js', 'vendor/d3/d3.min.js', 'vendor/qrcodejs', 'vendor/qr-scanner', 'vendor/war-room/war-room.js', 'system-guide-assets/fonts/vazirmatn.woff2'] as $relative) {
            $this->assertFileExists(public_path($relative));
        }
    }
}
