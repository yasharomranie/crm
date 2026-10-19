<?php

namespace App\Domain\Seo\WpConnector;

use App\Models\SeoWpPost;
use App\Models\SeoWpSite;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Stores what the plugin sent, keeping only what the site owner allowed:
 * a field whose scope is not granted is dropped here even if the plugin
 * sent it, and every text is trimmed to a sane size.
 */
class WpPostIngestor
{
    /** What each scope lets the plugin send, beyond the basic content facts. */
    public const SCOPES = ['content', 'seo_meta', 'structure', 'images', 'terms', 'authors', 'commerce', 'health', 'search_terms'];

    /** The slug column is VARCHAR(191) on a database that has not run the widening migration yet. */
    private const SLUG_LENGTH = 191;

    private const SEO_KEYS = ['focus_keyword', 'title', 'description', 'canonical', 'noindex', 'robots', 'plugin'];

    private const COMMERCE_KEYS = ['product_type', 'price', 'regular_price', 'sale_price', 'stock_status', 'sku', 'rating_average', 'review_count'];

    /**
     * @param  list<array<string, mixed>>  $posts
     * @param  list<int>  $deletedWpIds
     * @return array{accepted: int, deleted: int}
     */
    public function ingest(SeoWpSite $site, array $posts, array $deletedWpIds): array
    {
        $now = now();
        $rows = array_map(fn (array $post) => $this->row($site, $post, $now), $posts);

        DB::transaction(function () use ($site, $rows, $deletedWpIds) {
            if ($rows !== []) {
                SeoWpPost::upsert(
                    $rows,
                    ['seo_wp_site_id', 'wp_id'],
                    array_values(array_diff(array_keys($rows[0]), ['seo_wp_site_id', 'wp_id', 'created_at'])),
                );
            }

            if ($deletedWpIds !== []) {
                $site->posts()->whereIn('wp_id', $deletedWpIds)->delete();
            }
        });

        return ['accepted' => count($rows), 'deleted' => count($deletedWpIds)];
    }

    /**
     * @param  array<string, mixed>  $post
     * @return array<string, mixed>
     */
    private function row(SeoWpSite $site, array $post, Carbon $now): array
    {
        $url = Str::limit((string) $post['url'], 2000, '');

        return [
            'seo_wp_site_id' => $site->id,
            'wp_id' => (int) $post['wp_id'],
            'post_type' => Str::limit((string) $post['post_type'], 40, ''),
            'status' => Str::limit((string) $post['status'], 20, ''),
            'url' => $url,
            'url_hash' => SeoWpPost::hashUrl($url),
            'title' => Str::limit((string) ($post['title'] ?? ''), 500, ''),
            'slug' => isset($post['slug']) ? Str::limit((string) $post['slug'], self::SLUG_LENGTH, '') : null,
            'author_name' => $site->allows('authors') && isset($post['author_name']) ? Str::limit((string) $post['author_name'], 120, '') : null,
            'published_at' => $this->date($post['published_at'] ?? null),
            'modified_at' => $this->date($post['modified_at'] ?? null),
            'word_count' => max(0, (int) ($post['word_count'] ?? 0)),
            'headings' => $this->json($site->allows('structure') ? $this->headings($post['headings'] ?? null) : null),
            'internal_links' => $this->json($site->allows('structure') ? $this->links($post['internal_links'] ?? null, 300) : null),
            'external_links' => $this->json($site->allows('structure') ? $this->links($post['external_links'] ?? null, 100) : null),
            'images' => $this->json($site->allows('images') ? $this->images($post['images'] ?? null) : null),
            'terms' => $this->json($site->allows('terms') ? $this->terms($post['terms'] ?? null) : null),
            'seo' => $this->json($site->allows('seo_meta') ? $this->seo($post['seo'] ?? null) : null),
            'commerce' => $this->json($site->allows('commerce') && $site->has_woocommerce ? $this->commerce($post['commerce'] ?? null) : null),
            'content_hash' => isset($post['content_hash']) ? Str::limit((string) $post['content_hash'], 40, '') : null,
            'synced_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->utc();
        } catch (\Throwable) {
            return null;
        }
    }

    private function json(mixed $value): ?string
    {
        return $value === null ? null : json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return list<array{level: int, text: string}>|null
     */
    private function headings(mixed $headings): ?array
    {
        if (! is_array($headings)) {
            return null;
        }

        return collect($headings)
            ->filter(fn ($heading) => is_array($heading) && isset($heading['text']))
            ->map(fn (array $heading) => [
                'level' => max(1, min(6, (int) ($heading['level'] ?? 2))),
                'text' => Str::limit(trim((string) $heading['text']), 200, ''),
            ])
            ->take(60)
            ->values()
            ->all();
    }

    /**
     * @return list<array{url: string, anchor: string, nofollow: bool}>|null
     */
    private function links(mixed $links, int $limit): ?array
    {
        if (! is_array($links)) {
            return null;
        }

        return collect($links)
            ->filter(fn ($link) => is_array($link) && filled($link['url'] ?? null))
            ->map(fn (array $link) => [
                'url' => Str::limit((string) $link['url'], 2000, ''),
                'anchor' => Str::limit(trim((string) ($link['anchor'] ?? '')), 150, ''),
                'nofollow' => (bool) ($link['nofollow'] ?? false),
            ])
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return array{count: int, missing_alt: int}|null
     */
    private function images(mixed $images): ?array
    {
        if (! is_array($images)) {
            return null;
        }

        return [
            'count' => max(0, (int) ($images['count'] ?? 0)),
            'missing_alt' => max(0, (int) ($images['missing_alt'] ?? 0)),
        ];
    }

    /**
     * @return array{categories: list<string>, tags: list<string>}|null
     */
    private function terms(mixed $terms): ?array
    {
        if (! is_array($terms)) {
            return null;
        }

        $clean = fn (mixed $list) => collect(is_array($list) ? $list : [])
            ->filter(fn ($name) => is_string($name) && $name !== '')
            ->map(fn (string $name) => Str::limit($name, 100, ''))
            ->take(30)
            ->values()
            ->all();

        return ['categories' => $clean($terms['categories'] ?? []), 'tags' => $clean($terms['tags'] ?? [])];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function seo(mixed $seo): ?array
    {
        if (! is_array($seo)) {
            return null;
        }

        return collect($seo)
            ->only(self::SEO_KEYS)
            ->map(fn ($value) => is_string($value) ? Str::limit($value, 1000, '') : $value)
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function commerce(mixed $commerce): ?array
    {
        if (! is_array($commerce)) {
            return null;
        }

        return collect($commerce)
            ->only(self::COMMERCE_KEYS)
            ->map(fn ($value) => is_string($value) ? Str::limit($value, 100, '') : $value)
            ->all();
    }
}
