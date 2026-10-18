<?php

namespace App\Domain\Seo\WpConnector;

use App\Enums\SeoContentItemStatus;
use App\Models\SeoContentItem;
use App\Models\SeoPageMetric;
use App\Models\SeoProject;
use App\Models\SeoWpPost;
use Illuminate\Support\Collection;

/**
 * Closes the loop between the content calendar and the WordPress site: when
 * a planned article shows up as a published page (or an article marked for
 * rewriting has been edited since), the calendar row is marked published
 * with that page's address - so the measurement step (clicks and position
 * since publishing) starts by itself instead of waiting for someone to
 * remember to paste the URL.
 *
 * A page belongs to a calendar row when its focus keyword is the row's
 * target keyword, or its title holds every word of it. A page is never
 * given to two rows.
 */
class WpPublishedContentMatcher
{
    /**
     * @return int how many calendar rows were marked published
     */
    public function match(SeoProject $project): int
    {
        $items = SeoContentItem::query()
            ->where('seo_project_id', $project->id)
            ->where('status', '!=', SeoContentItemStatus::Published->value)
            ->whereNotNull('target_keyword')
            ->get();

        if ($items->isEmpty()) {
            return 0;
        }

        $posts = SeoWpPost::query()
            ->whereHas('site', fn ($site) => $site->where('seo_project_id', $project->id))
            ->where('status', 'publish')
            ->whereIn('post_type', ['post', 'page', 'product'])
            ->get(['id', 'title', 'url', 'url_hash', 'seo', 'published_at', 'modified_at']);

        if ($posts->isEmpty()) {
            return 0;
        }

        $taken = SeoContentItem::query()
            ->where('seo_project_id', $project->id)
            ->whereNotNull('published_url')
            ->pluck('published_url')
            ->map(fn (string $url) => WpUrl::key($url))
            ->flip();

        $matched = 0;

        foreach ($items as $item) {
            $post = $this->postFor($item, $posts, $taken);

            if (! $post) {
                continue;
            }

            $rewrite = $item->status === SeoContentItemStatus::NeedsUpdate;

            $item->update([
                'status' => SeoContentItemStatus::Published,
                'published_url' => $this->addressInSearchConsole($project, $post),
                'published_at' => $rewrite ? $post->modified_at : $post->published_at,
            ]);

            $taken->put($post->url_hash, true);
            $matched++;
        }

        return $matched;
    }

    /**
     * @param  Collection<int, SeoWpPost>  $posts
     * @param  Collection<string, int>  $taken
     */
    private function postFor(SeoContentItem $item, Collection $posts, Collection $taken): ?SeoWpPost
    {
        $keyword = WpContentInventory::canonical($item->target_keyword);
        $words = WpContentInventory::tokens($item->target_keyword);

        if ($keyword === '') {
            return null;
        }

        $rewrite = $item->status === SeoContentItemStatus::NeedsUpdate;

        $candidates = $posts
            ->reject(fn (SeoWpPost $post) => $taken->has($post->url_hash))
            ->filter(function (SeoWpPost $post) use ($item, $rewrite) {
                // A new article must have come out after its row was made; a rewrite must have been edited after it.
                $moment = $rewrite ? $post->modified_at : $post->published_at;

                return $moment && $moment->gte($item->created_at->copy()->subDay());
            })
            ->map(function (SeoWpPost $post) use ($keyword, $words) {
                if (WpContentInventory::canonical((string) ($post->seo['focus_keyword'] ?? '')) === $keyword) {
                    return ['post' => $post, 'score' => 2];
                }

                $title = WpContentInventory::tokens($post->title);

                return ['post' => $post, 'score' => $words !== [] && array_diff($words, $title) === [] ? 1 : 0];
            })
            ->filter(fn (array $row) => $row['score'] > 0);

        $best = $candidates->sortByDesc(fn (array $row) => [$row['score'], $row['post']->published_at?->timestamp])->first();

        return $best['post'] ?? null;
    }

    /**
     * Search Console may spell the address differently from WordPress (a
     * trailing slash, encoding); the measurement matches addresses exactly,
     * so use Search Console's own spelling when it has one.
     */
    private function addressInSearchConsole(SeoProject $project, SeoWpPost $post): string
    {
        $seen = SeoPageMetric::query()
            ->where('seo_project_id', $project->id)
            ->distinct()
            ->pluck('page_url')
            ->first(fn (string $url) => WpUrl::key($url) === $post->url_hash);

        return $seen ?? $post->url;
    }
}
