<?php

namespace App\Domain\Seo\WpConnector;

use App\Models\SeoWpPost;
use App\Models\SeoWpSite;
use Illuminate\Support\Facades\DB;

/**
 * Works out, for every page of a connected WordPress site, how many pages
 * link to it, how it did in Search Console (last 28 days against the 28
 * before) and which problems it has - and stores the result on the page so
 * the list can be sorted and filtered in the database.
 */
class WpSiteAnalyzer
{
    /** Search Console data arrives about three days late. */
    private const LAG_DAYS = 3;

    private const WINDOW_DAYS = 28;

    public function analyze(SeoWpSite $site): void
    {
        $structureShared = $site->allows('structure');
        $inbound = $structureShared ? $this->inboundCounts($site) : [];
        [$metrics, $hasGsc] = $this->searchConsole($site);
        $tracksKeyEvents = (int) ($site->project?->googleConnection?->ga_summary['key_events'] ?? 0) > 0;

        $site->posts()
            ->select(['id', 'url', 'url_hash', 'status', 'post_type', 'word_count', 'seo', 'images', 'published_at', 'inbound_links', 'gsc_clicks', 'gsc_impressions', 'gsc_ctr', 'gsc_position', 'gsc_prev_clicks', 'ga_sessions', 'ga_key_events', 'issues'])
            ->chunkById(200, function ($posts) use ($structureShared, $inbound, $metrics, $hasGsc, $tracksKeyEvents) {
                foreach ($posts as $post) {
                    $this->analyzePost($post, $structureShared ? ($inbound[$post->url_hash] ?? 0) : null, $metrics[$post->url_hash] ?? null, $hasGsc, $tracksKeyEvents);
                }
            });

        $site->update(['analyzed_at' => now()]);
    }

    /**
     * @param  array{clicks: int, impressions: int, ctr: float, position: float, prev_clicks: int}|null  $gsc
     */
    private function analyzePost(SeoWpPost $post, ?int $inbound, ?array $gsc, bool $hasGsc, bool $tracksKeyEvents): void
    {
        $issues = WpPostIssues::detect([
            'status' => $post->status,
            'post_type' => $post->post_type,
            'url' => $post->url,
            'word_count' => $post->word_count,
            'seo' => $post->seo,
            'images' => $post->images,
            'inbound' => $inbound,
            'published_at' => $post->published_at,
            'gsc' => $gsc,
            'has_gsc' => $hasGsc,
            'ga' => $post->ga_sessions === null ? null : [
                'sessions' => (int) $post->ga_sessions,
                'key_events' => (int) $post->ga_key_events,
                'site_tracks_key_events' => $tracksKeyEvents,
            ],
        ]);

        $fresh = [
            'inbound_links' => $inbound,
            'gsc_clicks' => $gsc['clicks'] ?? null,
            'gsc_impressions' => $gsc['impressions'] ?? null,
            'gsc_ctr' => $gsc['ctr'] ?? null,
            'gsc_position' => $gsc['position'] ?? null,
            'gsc_prev_clicks' => $gsc['prev_clicks'] ?? null,
            'issues' => json_encode($issues),
        ];

        $current = [
            'inbound_links' => $post->inbound_links,
            'gsc_clicks' => $post->gsc_clicks,
            'gsc_impressions' => $post->gsc_impressions,
            'gsc_ctr' => $post->gsc_ctr === null ? null : (float) $post->gsc_ctr,
            'gsc_position' => $post->gsc_position === null ? null : (float) $post->gsc_position,
            'gsc_prev_clicks' => $post->gsc_prev_clicks,
            'issues' => json_encode($post->issues ?? []),
        ];

        if ($current != $fresh) {
            DB::table('seo_wp_posts')->where('id', $post->id)->update($fresh);
        }
    }

    /**
     * @return array<string, int> page key => number of other pages linking to it
     */
    private function inboundCounts(SeoWpSite $site): array
    {
        $counts = [];

        $site->posts()
            ->select(['id', 'url_hash', 'internal_links'])
            ->chunkById(200, function ($posts) use (&$counts) {
                foreach ($posts as $post) {
                    $targets = collect($post->internal_links ?? [])
                        ->map(fn (array $link) => WpUrl::key($link['url']))
                        ->unique()
                        ->reject(fn (string $key) => $key === $post->url_hash);

                    foreach ($targets as $key) {
                        $counts[$key] = ($counts[$key] ?? 0) + 1;
                    }
                }
            });

        return $counts;
    }

    /**
     * @return array{0: array<string, array{clicks: int, impressions: int, ctr: float, position: float, prev_clicks: int}>, 1: bool} metrics by page key, and whether the project has any Search Console data at all
     */
    private function searchConsole(SeoWpSite $site): array
    {
        $end = now()->subDays(self::LAG_DAYS)->startOfDay();
        $currentStart = $end->copy()->subDays(self::WINDOW_DAYS - 1);
        $previousStart = $currentStart->copy()->subDays(self::WINDOW_DAYS);
        $current = $currentStart->toDateString();

        $rows = DB::table('seo_page_metrics')
            ->where('seo_project_id', $site->seo_project_id)
            ->whereBetween('date', [$previousStart->toDateString(), $end->toDateString()])
            ->groupBy('page_url')
            ->selectRaw(
                'page_url,
                sum(case when date >= ? then clicks else 0 end) as clicks,
                sum(case when date >= ? then impressions else 0 end) as impressions,
                sum(case when date >= ? then position * impressions else 0 end) as weighted_position,
                sum(case when date < ? then clicks else 0 end) as prev_clicks',
                [$current, $current, $current, $current]
            )
            ->get();

        $metrics = [];

        foreach ($rows as $row) {
            $key = WpUrl::key($row->page_url);
            $metrics[$key]['clicks'] = ($metrics[$key]['clicks'] ?? 0) + (int) $row->clicks;
            $metrics[$key]['impressions'] = ($metrics[$key]['impressions'] ?? 0) + (int) $row->impressions;
            $metrics[$key]['weighted_position'] = ($metrics[$key]['weighted_position'] ?? 0) + (float) $row->weighted_position;
            $metrics[$key]['prev_clicks'] = ($metrics[$key]['prev_clicks'] ?? 0) + (int) $row->prev_clicks;
        }

        foreach ($metrics as $key => $sums) {
            $impressions = $sums['impressions'];
            $metrics[$key] = [
                'clicks' => $sums['clicks'],
                'impressions' => $impressions,
                'ctr' => $impressions > 0 ? round($sums['clicks'] / $impressions, 4) : 0.0,
                'position' => $impressions > 0 ? round($sums['weighted_position'] / $impressions, 2) : 0.0,
                'prev_clicks' => $sums['prev_clicks'],
            ];
        }

        return [$metrics, $rows->isNotEmpty()];
    }
}
