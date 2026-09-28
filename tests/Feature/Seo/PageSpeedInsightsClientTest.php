<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\PageSpeedInsightsClient;
use App\Exceptions\SeoGoogleException;
use App\Models\SeoRankTrackerSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PageSpeedInsightsClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_parses_the_performance_score_and_core_web_vitals(): void
    {
        SeoRankTrackerSetting::current()->update(['api_key' => 'test-key', 'search_engine_id' => 'cx']);

        Http::fake([
            'www.googleapis.com/pagespeedonline/*' => Http::response([
                'lighthouseResult' => [
                    'categories' => ['performance' => ['score' => 0.73]],
                    'audits' => [
                        'largest-contentful-paint' => ['numericValue' => 2450.6],
                        'cumulative-layout-shift' => ['numericValue' => 0.12],
                    ],
                ],
                'loadingExperience' => ['overall_category' => 'AVERAGE'],
            ]),
        ]);

        $result = app(PageSpeedInsightsClient::class)->analyze('https://example.com');

        $this->assertSame(73, $result['performance_score']);
        $this->assertSame(2450.6, $result['lcp_ms']);
        $this->assertSame(0.12, $result['cls']);
        $this->assertSame('AVERAGE', $result['field_data_category']);
    }

    public function test_it_throws_when_no_api_key_is_configured(): void
    {
        $this->expectException(SeoGoogleException::class);

        app(PageSpeedInsightsClient::class)->analyze('https://example.com');
    }

    public function test_it_throws_when_the_request_fails(): void
    {
        SeoRankTrackerSetting::current()->update(['api_key' => 'test-key', 'search_engine_id' => 'cx']);

        Http::fake([
            'www.googleapis.com/pagespeedonline/*' => Http::response(['error' => ['message' => 'Invalid URL']], 400),
        ]);

        $this->expectException(SeoGoogleException::class);

        app(PageSpeedInsightsClient::class)->analyze('https://example.com');
    }

    public function test_missing_field_data_is_handled_gracefully(): void
    {
        SeoRankTrackerSetting::current()->update(['api_key' => 'test-key', 'search_engine_id' => 'cx']);

        // A brand-new/low-traffic site has no CrUX field data at all -
        // loadingExperience is simply absent from the response.
        Http::fake([
            'www.googleapis.com/pagespeedonline/*' => Http::response([
                'lighthouseResult' => [
                    'categories' => ['performance' => ['score' => 0.5]],
                    'audits' => [],
                ],
            ]),
        ]);

        $result = app(PageSpeedInsightsClient::class)->analyze('https://example.com');

        $this->assertSame(50, $result['performance_score']);
        $this->assertNull($result['lcp_ms']);
        $this->assertNull($result['field_data_category']);
    }
}
