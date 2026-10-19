<?php

namespace App\Domain\Seo\WpConnector;

use App\Domain\Seo\GoogleAutocompleteClient;
use App\Models\SeoKeywordProfile;
use App\Models\SeoProject;
use App\Models\SeoWpPost;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * A writer's brief built from what the project really has: the pages that
 * share words with the target search (to link to and from - they are the
 * internal links of the new article), the other searches Google already
 * shows the site for on the same subject (secondary keywords), and, when an
 * existing page is to be rewritten, that page's own weak points.
 * Nothing here calls an AI - it is plain facts from the plugin, Search Console
 * and the suggestions Google's own search box gives.
 */
class WpContentBrief
{
    private const MIN_WORD_LENGTH = 3;

    private const MAX_LINKS = 6;

    private const MAX_RELATED_SEARCHES = 6;

    public function __construct(private readonly GoogleAutocompleteClient $autocomplete) {}

    public function build(SeoProject $project, string $query, ?SeoWpPost $target = null): string
    {
        $words = $this->words($query);
        $lines = [];

        if ($target) {
            $lines[] = 'صفحه‌ی فعلی: '.$target->url;
            $lines[] = $this->targetFacts($target);
        }

        $related = $this->relatedPages($project, $words, $target);

        if ($related->isNotEmpty()) {
            $lines[] = '';
            $lines[] = $target
                ? 'به این صفحه‌ها از متن لینک بدهید (و از آن‌ها هم به این صفحه):'
                : 'لینک داخلی: در مقاله به این صفحه‌ها لینک بدهید و از آن‌ها هم به مقاله‌ی تازه:';

            foreach ($related as $post) {
                $lines[] = '- '.Str::limit($post->title, 80, '…').' — '.$post->url;
            }
        }

        $searches = $this->relatedSearches($project, $query, $words);

        if ($searches->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'عبارت‌های هم‌خانواده که گوگل همین‌حالا به سایت نشان می‌دهد (در متن بیاورید): '.$searches->implode('، ');
        }

        $typed = $this->autocomplete->suggestionsFor($query);

        if ($typed !== []) {
            $lines[] = '';
            $lines[] = 'آنچه مردم در گوگل برای این موضوع تایپ می‌کنند (سؤال‌ها و بخش‌های مقاله): '.implode('، ', $typed);
        }

        return implode("\n", array_filter($lines, fn ($line) => $line !== null));
    }

    private function targetFacts(SeoWpPost $post): string
    {
        $facts = [];

        if ($post->word_count) {
            $facts[] = number_format($post->word_count).' کلمه';
        }

        $headingCount = count($post->headings ?? []);
        $facts[] = $headingCount > 0 ? "{$headingCount} سرتیتر" : 'بدون سرتیتر';

        if ($post->inbound_links !== null) {
            $facts[] = $post->inbound_links.' لینک داخلی ورودی';
        }

        if ($post->modified_at) {
            $facts[] = 'آخرین ویرایش '.$post->modified_at->diffForHumans();
        }

        $issues = collect($post->issues ?? [])
            ->map(fn ($code) => WpPostIssues::label((string) $code))
            ->implode('، ');

        return 'وضعیت: '.implode('، ', $facts).($issues !== '' ? ". مشکل‌ها: {$issues}" : '');
    }

    /**
     * @param  list<string>  $words
     * @return Collection<int, SeoWpPost>
     */
    private function relatedPages(SeoProject $project, array $words, ?SeoWpPost $target): Collection
    {
        if ($words === []) {
            return collect();
        }

        return SeoWpPost::query()
            ->whereHas('site', fn ($site) => $site->where('seo_project_id', $project->id))
            ->where('status', 'publish')
            ->whereIn('post_type', ['post', 'page', 'product'])
            ->when($target, fn ($query) => $query->whereKeyNot($target->id))
            ->get(['id', 'title', 'url', 'gsc_clicks', 'seo'])
            ->map(function (SeoWpPost $post) use ($words) {
                $haystack = $this->words($post->title.' '.($post->seo['focus_keyword'] ?? ''));

                return ['post' => $post, 'shared' => count(array_intersect($words, $haystack))];
            })
            ->filter(fn (array $row) => $row['shared'] > 0)
            ->sortByDesc(fn (array $row) => [$row['shared'], (int) $row['post']->gsc_clicks])
            ->take(self::MAX_LINKS)
            ->pluck('post')
            ->values();
    }

    /**
     * @param  list<string>  $words
     * @return Collection<int, string>
     */
    private function relatedSearches(SeoProject $project, string $query, array $words): Collection
    {
        if ($words === []) {
            return collect();
        }

        $own = WpContentInventory::canonical($query);

        return SeoKeywordProfile::query()
            ->where('seo_project_id', $project->id)
            ->orderByDesc('impressions_28d')
            ->limit(300)
            ->pluck('query')
            ->filter(fn (string $other) => WpContentInventory::canonical($other) !== $own
                && array_intersect($words, $this->words($other)) !== [])
            ->take(self::MAX_RELATED_SEARCHES)
            ->values();
    }

    /**
     * @return list<string> the meaningful words of a text (short filler words dropped)
     */
    private function words(string $text): array
    {
        return array_values(array_unique(array_filter(
            WpContentInventory::tokens($text),
            fn (string $word) => mb_strlen($word) >= self::MIN_WORD_LENGTH,
        )));
    }
}
