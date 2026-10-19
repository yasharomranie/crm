<?php

namespace App\Domain\Seo;

use App\Domain\Seo\WpConnector\WpUrl;
use App\Exceptions\SeoGoogleException;
use App\Models\SeoProject;
use App\Models\SeoWpPost;

/**
 * Pulls the last 28 days from the project's Google Analytics 4 property:
 * sessions and key events per page (written onto the matching WordPress
 * posts), the site's totals, and how many visits came from AI assistants
 * such as ChatGPT or Perplexity - the one number Search Console cannot give.
 */
class GoogleAnalyticsSyncService
{
    /** Referrer hosts GA4 reports as the session source for AI assistants. */
    public const AI_SOURCES = [
        'chatgpt.com', 'chat.openai.com', 'perplexity.ai', 'www.perplexity.ai', 'gemini.google.com',
        'copilot.microsoft.com', 'claude.ai', 'you.com', 'poe.com', 'deepseek.com', 'chat.deepseek.com',
    ];

    private const PAGE_LIMIT = 10000;

    public function __construct(private readonly GoogleOAuthService $oauth) {}

    public function isReady(SeoProject $project): bool
    {
        $connection = $project->googleConnection;

        return $connection !== null
            && filled($connection->ga4_property_id)
            && $connection->canReadAnalytics();
    }

    /**
     * @throws SeoGoogleException
     */
    public function sync(SeoProject $project): void
    {
        $connection = $project->googleConnection;

        if (! $connection || blank($connection->ga4_property_id)) {
            throw SeoGoogleException::notConnected();
        }

        if (! $connection->canReadAnalytics()) {
            throw SeoGoogleException::requestFailed('دسترسی Google Analytics داده نشده؛ حساب گوگل را دوباره وصل کنید.');
        }

        $client = new GoogleAnalyticsClient($this->oauth->freshAccessTokenFor($connection));
        $property = (string) $connection->ga4_property_id;
        $range = [['startDate' => '28daysAgo', 'endDate' => 'yesterday']];

        $pages = $client->runReport($property, [
            'dateRanges' => $range,
            'dimensions' => [['name' => 'pagePath']],
            'metrics' => [['name' => 'sessions'], ['name' => 'keyEvents']],
            'limit' => self::PAGE_LIMIT,
        ]);

        $totals = $client->runReport($property, [
            'dateRanges' => $range,
            'metrics' => [['name' => 'sessions'], ['name' => 'keyEvents'], ['name' => 'totalRevenue']],
        ]);

        $ai = $client->runReport($property, [
            'dateRanges' => $range,
            'dimensions' => [['name' => 'sessionSource']],
            'metrics' => [['name' => 'sessions']],
            'dimensionFilter' => ['filter' => [
                'fieldName' => 'sessionSource',
                'inListFilter' => ['values' => self::AI_SOURCES],
            ]],
        ]);

        $this->writePages($project, $pages);

        $aiSources = collect($ai)->mapWithKeys(fn (array $row) => [$row['dimensions'][0] => (int) $row['metrics'][0]])->all();

        $connection->update([
            'ga_summary' => [
                'sessions' => (int) ($totals[0]['metrics'][0] ?? 0),
                'key_events' => (int) ($totals[0]['metrics'][1] ?? 0),
                'revenue' => round((float) ($totals[0]['metrics'][2] ?? 0), 2),
                'ai_sessions' => array_sum($aiSources),
                'ai_sources' => $aiSources,
                'days' => 28,
            ],
            'ga_synced_at' => now(),
            'ga_last_error' => null,
        ]);
    }

    /**
     * @param  array<int, array{dimensions: list<string>, metrics: list<float>}>  $rows
     */
    private function writePages(SeoProject $project, array $rows): void
    {
        $byPath = [];

        foreach ($rows as $row) {
            $key = WpUrl::pathKey($row['dimensions'][0] ?? '');
            $byPath[$key]['sessions'] = ($byPath[$key]['sessions'] ?? 0) + (int) $row['metrics'][0];
            $byPath[$key]['key_events'] = ($byPath[$key]['key_events'] ?? 0) + (int) $row['metrics'][1];
        }

        SeoWpPost::query()
            ->whereHas('site', fn ($site) => $site->where('seo_project_id', $project->id))
            ->select(['id', 'url', 'ga_sessions', 'ga_key_events'])
            ->chunkById(200, function ($posts) use ($byPath) {
                foreach ($posts as $post) {
                    $fresh = $byPath[WpUrl::pathKey($post->url)] ?? ['sessions' => 0, 'key_events' => 0];

                    if ($post->ga_sessions === null || (int) $post->ga_sessions !== $fresh['sessions'] || (int) $post->ga_key_events !== $fresh['key_events']) {
                        $post->forceFill(['ga_sessions' => $fresh['sessions'], 'ga_key_events' => $fresh['key_events']])->saveQuietly();
                    }
                }
            });
    }
}
