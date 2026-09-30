<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\SitemapClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SitemapClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_parses_a_plain_urlset(): void
    {
        Http::fake([
            'ganjnameh.example.com/sitemap.xml' => Http::response(<<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                    <url>
                        <loc>https://ganjnameh.example.com/telecabin</loc>
                        <lastmod>2026-09-20T10:00:00+00:00</lastmod>
                    </url>
                    <url>
                        <loc>https://ganjnameh.example.com/about</loc>
                    </url>
                </urlset>
                XML, 200),
        ]);

        $entries = (new SitemapClient)->fetch('https://ganjnameh.example.com/sitemap.xml');

        $this->assertCount(2, $entries);
        $this->assertSame('https://ganjnameh.example.com/telecabin', $entries[0]['loc']);
        $this->assertSame('2026-09-20T10:00:00+00:00', $entries[0]['lastmod']);
        $this->assertSame('https://ganjnameh.example.com/about', $entries[1]['loc']);
        $this->assertNull($entries[1]['lastmod']);
    }

    public function test_it_recurses_into_a_sitemap_index(): void
    {
        Http::fake([
            'ganjnameh.example.com/sitemap.xml' => Http::response(<<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                    <sitemap><loc>https://ganjnameh.example.com/sitemap-pages.xml</loc></sitemap>
                    <sitemap><loc>https://ganjnameh.example.com/sitemap-posts.xml</loc></sitemap>
                </sitemapindex>
                XML, 200),
            'ganjnameh.example.com/sitemap-pages.xml' => Http::response(<<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                    <url><loc>https://ganjnameh.example.com/telecabin</loc></url>
                </urlset>
                XML, 200),
            'ganjnameh.example.com/sitemap-posts.xml' => Http::response(<<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                    <url><loc>https://ganjnameh.example.com/blog/post-1</loc></url>
                </urlset>
                XML, 200),
        ]);

        $entries = (new SitemapClient)->fetch('https://ganjnameh.example.com/sitemap.xml');

        $this->assertCount(2, $entries);
        $this->assertSame(
            ['https://ganjnameh.example.com/telecabin', 'https://ganjnameh.example.com/blog/post-1'],
            array_column($entries, 'loc'),
        );
    }

    public function test_a_broken_child_sitemap_does_not_stop_the_others_from_being_read(): void
    {
        Http::fake([
            'ganjnameh.example.com/sitemap.xml' => Http::response(<<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                    <sitemap><loc>https://ganjnameh.example.com/sitemap-broken.xml</loc></sitemap>
                    <sitemap><loc>https://ganjnameh.example.com/sitemap-ok.xml</loc></sitemap>
                </sitemapindex>
                XML, 200),
            'ganjnameh.example.com/sitemap-broken.xml' => Http::response('', 500),
            'ganjnameh.example.com/sitemap-ok.xml' => Http::response(<<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                    <url><loc>https://ganjnameh.example.com/telecabin</loc></url>
                </urlset>
                XML, 200),
        ]);

        $entries = (new SitemapClient)->fetch('https://ganjnameh.example.com/sitemap.xml');

        $this->assertCount(1, $entries);
        $this->assertSame('https://ganjnameh.example.com/telecabin', $entries[0]['loc']);
    }

    public function test_it_decompresses_a_gzipped_sitemap(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
                <url><loc>https://ganjnameh.example.com/telecabin</loc></url>
            </urlset>
            XML;

        Http::fake([
            'ganjnameh.example.com/sitemap.xml.gz' => Http::response(gzencode($xml), 200),
        ]);

        $entries = (new SitemapClient)->fetch('https://ganjnameh.example.com/sitemap.xml.gz');

        $this->assertCount(1, $entries);
        $this->assertSame('https://ganjnameh.example.com/telecabin', $entries[0]['loc']);
    }

    public function test_it_throws_when_the_sitemap_request_fails(): void
    {
        Http::fake([
            'ganjnameh.example.com/sitemap.xml' => Http::response('', 404),
        ]);

        $this->expectException(\RuntimeException::class);

        (new SitemapClient)->fetch('https://ganjnameh.example.com/sitemap.xml');
    }

    public function test_it_throws_when_the_xml_root_is_neither_urlset_nor_sitemapindex(): void
    {
        Http::fake([
            'ganjnameh.example.com/sitemap.xml' => Http::response('<somethingElse></somethingElse>', 200),
        ]);

        $this->expectException(\RuntimeException::class);

        (new SitemapClient)->fetch('https://ganjnameh.example.com/sitemap.xml');
    }
}
