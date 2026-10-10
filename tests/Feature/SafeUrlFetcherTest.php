<?php

namespace Tests\Feature;

use App\Domain\Ai\SafeUrlFetcher;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SafeUrlFetcherTest extends TestCase
{
    public function test_it_fetches_and_strips_html_from_a_public_address(): void
    {
        Http::fake([
            'http://8.8.8.8/page' => Http::response('<html><head><style>body{color:red}</style></head><body><script>evil()</script><h1>سلام دنیا</h1><p>متن   با &nbsp; فاصلهٔ   زیاد</p></body></html>'),
        ]);

        $result = app(SafeUrlFetcher::class)->fetch('http://8.8.8.8/page');

        $this->assertSame('http://8.8.8.8/page', $result['url']);
        $this->assertStringContainsString('سلام دنیا', $result['content']);
        $this->assertStringNotContainsString('evil()', $result['content']);
        $this->assertStringNotContainsString('color:red', $result['content']);
        $this->assertStringNotContainsString('  ', $result['content']);
    }

    public function test_it_truncates_very_long_content(): void
    {
        Http::fake([
            'http://8.8.8.8/long' => Http::response('<p>'.str_repeat('الف ', 5000).'</p>'),
        ]);

        $result = app(SafeUrlFetcher::class)->fetch('http://8.8.8.8/long');

        $this->assertLessThanOrEqual(8000, mb_strlen($result['content']));
    }

    public function test_it_reports_a_failed_response(): void
    {
        Http::fake([
            'http://8.8.8.8/missing' => Http::response('not found', 404),
        ]);

        $result = app(SafeUrlFetcher::class)->fetch('http://8.8.8.8/missing');

        $this->assertArrayHasKey('error', $result);
    }

    public function test_it_rejects_a_loopback_address(): void
    {
        $result = app(SafeUrlFetcher::class)->fetch('http://127.0.0.1/admin');

        $this->assertArrayHasKey('error', $result);
        Http::assertNothingSent();
    }

    public function test_it_rejects_a_private_network_address(): void
    {
        $result = app(SafeUrlFetcher::class)->fetch('http://192.168.1.1/');

        $this->assertArrayHasKey('error', $result);
        Http::assertNothingSent();
    }

    public function test_it_rejects_a_cloud_metadata_address(): void
    {
        $result = app(SafeUrlFetcher::class)->fetch('http://169.254.169.254/latest/meta-data/');

        $this->assertArrayHasKey('error', $result);
        Http::assertNothingSent();
    }

    public function test_it_rejects_a_non_http_scheme(): void
    {
        $result = app(SafeUrlFetcher::class)->fetch('file:///etc/passwd');

        $this->assertArrayHasKey('error', $result);
        Http::assertNothingSent();
    }

    public function test_it_rejects_a_disallowed_port(): void
    {
        $result = app(SafeUrlFetcher::class)->fetch('http://8.8.8.8:8080/');

        $this->assertArrayHasKey('error', $result);
        Http::assertNothingSent();
    }
}
