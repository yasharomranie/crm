<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\SearchConsoleClient;
use App\Exceptions\SeoGoogleException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SearchConsoleClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_verified_sites(): void
    {
        Http::fake([
            'www.googleapis.com/webmasters/v3/sites' => Http::response([
                'siteEntry' => [
                    ['siteUrl' => 'https://ganjnameh.example.com/', 'permissionLevel' => 'siteOwner'],
                ],
            ]),
        ]);

        $sites = (new SearchConsoleClient('token-123'))->listSites();

        $this->assertSame('https://ganjnameh.example.com/', $sites[0]['siteUrl']);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer token-123'));
    }

    public function test_it_queries_search_analytics_with_the_given_dimensions(): void
    {
        Http::fake([
            '*searchAnalytics/query' => Http::response([
                'rows' => [
                    ['keys' => ['2026-09-01', 'تله کابین گنجنامه'], 'clicks' => 12, 'impressions' => 300, 'ctr' => 0.04, 'position' => 5.2],
                ],
            ]),
        ]);

        $rows = (new SearchConsoleClient('token-123'))->querySearchAnalytics(
            'https://ganjnameh.example.com/',
            '2026-09-01',
            '2026-09-28',
            ['date', 'query'],
        );

        $this->assertSame(12, $rows[0]['clicks']);

        Http::assertSent(fn ($request) => $request['dimensions'] === ['date', 'query']
            && $request['startDate'] === '2026-09-01'
            && $request['rowLimit'] === SearchConsoleClient::MAX_ROW_LIMIT);
    }

    public function test_it_caps_the_row_limit_at_googles_own_maximum(): void
    {
        Http::fake(['*searchAnalytics/query' => Http::response(['rows' => []])]);

        (new SearchConsoleClient('token-123'))->querySearchAnalytics(
            'https://ganjnameh.example.com/',
            '2026-09-01',
            '2026-09-28',
            ['date'],
            rowLimit: 999999,
        );

        Http::assertSent(fn ($request) => $request['rowLimit'] === SearchConsoleClient::MAX_ROW_LIMIT);
    }

    public function test_it_throws_with_googles_error_message_on_failure(): void
    {
        Http::fake([
            '*searchAnalytics/query' => Http::response(['error' => ['message' => 'دسترسی به این ملک رد شد.']], 403),
        ]);

        $this->expectException(SeoGoogleException::class);
        $this->expectExceptionMessage('دسترسی به این ملک رد شد.');

        (new SearchConsoleClient('token-123'))->querySearchAnalytics(
            'https://ganjnameh.example.com/',
            '2026-09-01',
            '2026-09-28',
            ['date'],
        );
    }
}
