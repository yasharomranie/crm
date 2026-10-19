<?php

namespace App\Domain\Seo\WpConnector;

use App\Models\SeoContentItem;
use App\Models\SeoKeywordProfile;
use App\Models\SeoProject;
use App\Models\SeoWpPost;
use App\Models\SeoWpSearchTerm;
use Illuminate\Support\Collection;

/**
 * Sets what Google already shows the site for (the keyword profiles from
 * Search Console) against what the site actually has (the pages the
 * WordPress plugin reported):
 *
 *  - "uncovered": a search with real impressions, not served well yet
 *    (outside the top 10), that no page targets - a new article is the answer;
 *  - "improve": a search a page of the site already ranks for, but on page
 *    two or three (position 11-30) - rewriting that page beats writing a
 *    new one.
 *
 * A search already planned in the content calendar is left out.
 */
class WpCoverageGaps
{
    /** Small sites have few impressions per search, so the default is low; the page lets the user raise it. */
    public const DEFAULT_MIN_IMPRESSIONS = 10;

    public const MIN_IMPRESSIONS_CHOICES = [5, 10, 30, 50, 100];

    private const LIMIT = 50;

    /** A site search must have found nothing this often to count as a content wish rather than a typo. */
    private const MIN_SITE_SEARCH_MISSES = 2;

    /**
     * @return array{has_pages: bool, has_searches: bool, searches_total: int, searches_considered: int, min_impressions: int, site_searches: Collection<int, array<string, mixed>>, uncovered: Collection<int, array<string, mixed>>, improve: Collection<int, array<string, mixed>>}
     */
    public function analyze(SeoProject $project, int $minImpressions = self::DEFAULT_MIN_IMPRESSIONS): array
    {
        $posts = SeoWpPost::query()
            ->whereHas('site', fn ($site) => $site->where('seo_project_id', $project->id))
            ->where('status', 'publish')
            ->whereIn('post_type', ['post', 'page', 'product'])
            ->get(['id', 'title', 'url', 'url_hash', 'seo']);

        $searchesTotal = SeoKeywordProfile::query()->where('seo_project_id', $project->id)->count();

        $profiles = SeoKeywordProfile::query()
            ->where('seo_project_id', $project->id)
            ->where('impressions_28d', '>=', $minImpressions)
            ->orderByDesc('impressions_28d')
            ->limit(500)
            ->get();

        $result = [
            'has_pages' => $posts->isNotEmpty(),
            'has_searches' => $searchesTotal > 0,
            'site_searches' => $this->siteSearches($project),
            'searches_total' => $searchesTotal,
            'searches_considered' => $profiles->count(),
            'min_impressions' => $minImpressions,
            'uncovered' => collect(),
            'improve' => collect(),
        ];

        if ($posts->isEmpty() || $profiles->isEmpty()) {
            return $result;
        }

        $planned = SeoContentItem::query()
            ->where('seo_project_id', $project->id)
            ->whereNotNull('target_keyword')
            ->pluck('target_keyword')
            ->map(fn (string $keyword) => WpContentInventory::canonical($keyword))
            ->flip();

        $byKey = $posts->keyBy('url_hash');
        $focus = $posts->map(fn (SeoWpPost $post) => WpContentInventory::canonical((string) ($post->seo['focus_keyword'] ?? '')))->filter()->unique()->values();
        $titleWords = $posts->map(fn (SeoWpPost $post) => WpContentInventory::tokens($post->title));

        foreach ($profiles as $profile) {
            $position = (float) $profile->current_position;
            $query = WpContentInventory::canonical($profile->query);

            if ($position <= 10 || $planned->has($query)) {
                continue;
            }

            $landing = $profile->landing_page ? $byKey->get(WpUrl::key($profile->landing_page)) : null;

            $row = [
                'query' => $profile->query,
                'impressions' => (int) $profile->impressions_28d,
                'clicks' => (int) $profile->clicks_28d,
                'position' => round($position, 1),
            ];

            if ($landing && $position <= 30) {
                $result['improve']->push($row + ['post_id' => $landing->id, 'post_title' => $landing->title, 'post_url' => $landing->url]);
            } elseif (! $this->isTargeted($query, $focus, $titleWords)) {
                $result['uncovered']->push($row + ['landing' => $profile->landing_page]);
            }
        }

        $result['uncovered'] = $result['uncovered']->take(self::LIMIT)->values();
        $result['improve'] = $result['improve']->take(self::LIMIT)->values();

        return $result;
    }

    /**
     * A page targets a search when its focus keyword is that search (or one
     * contains the other, for multi-word focus keywords), or its title holds
     * every word of the search.
     *
     * @param  Collection<int, string>  $focus
     * @param  Collection<int, array<int, string>>  $titleWords
     */
    private function isTargeted(string $query, Collection $focus, Collection $titleWords): bool
    {
        foreach ($focus as $keyword) {
            if ($keyword === $query
                || (count(explode(' ', $keyword)) >= 2 && (str_contains($query, $keyword) || str_contains($keyword, $query)))) {
                return true;
            }
        }

        $words = array_values(array_filter(explode(' ', $query)));

        foreach ($titleWords as $title) {
            if ($words !== [] && array_diff($words, $title) === []) {
                return true;
            }
        }

        return false;
    }

    /**
     * What visitors typed into the site's own search box and found nothing
     * for - content they wanted that the site does not have. Terms already
     * planned in the calendar are left out.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function siteSearches(SeoProject $project): Collection
    {
        $planned = SeoContentItem::query()
            ->where('seo_project_id', $project->id)
            ->whereNotNull('target_keyword')
            ->pluck('target_keyword')
            ->map(fn (string $keyword) => WpContentInventory::canonical($keyword))
            ->flip();

        return SeoWpSearchTerm::query()
            ->whereHas('site', fn ($site) => $site->where('seo_project_id', $project->id))
            ->where('misses', '>=', self::MIN_SITE_SEARCH_MISSES)
            ->orderByDesc('misses')
            ->limit(self::LIMIT)
            ->get()
            ->reject(fn (SeoWpSearchTerm $term) => $planned->has(WpContentInventory::canonical($term->term)))
            ->map(fn (SeoWpSearchTerm $term) => ['query' => $term->term, 'count' => $term->count, 'misses' => $term->misses])
            ->values();
    }
}
