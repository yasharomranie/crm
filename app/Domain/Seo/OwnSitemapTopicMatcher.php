<?php

namespace App\Domain\Seo;

use App\Domain\Seo\Concerns\ParsesAiJsonArray;
use App\Exceptions\AiProviderException;
use App\Models\AiProvider;
use App\Models\SeoProject;
use App\Models\SeoSitemapEntry;
use App\Models\SeoTopicCluster;
use Illuminate\Support\Collection;

/**
 * Classifies each newly-synced page in a project's OWN sitemap
 * (App\Models\SeoSitemapEntry) into one of the project's topic clusters
 * - or a genuinely new one - the same job App\Domain\Seo\
 * CompetitorTopicMatcher does for a competitor's sitemap, applied to the
 * project's own. This is what lets App\Domain\Seo\
 * AiKeywordDiscoveryService and App\Domain\Seo\
 * CompetitorContentGapService::detect() both ask "does this project
 * already have a real, published page for this topic" against the
 * actual live site, instead of only the content calendar (App\Models\
 * SeoContentItem), which an operator could easily forget to log a page
 * in.
 *
 * Same cheap lexical pre-filter as the competitor version, for the same
 * reason: a sitemap is full of pages no classifier needs to see at all
 * (login, cart, category/tag index, legal pages, pagination).
 */
class OwnSitemapTopicMatcher
{
    use ParsesAiJsonArray;

    private const BATCH_SIZE = 25;

    /**
     * @var array<int, string>
     */
    private const NOISE_PATH_PATTERNS = [
        '/wp-admin', '/wp-json', '/wp-login', '/login', '/account', '/cart',
        '/checkout', '/category/', '/tag/', '/page/', '/author/', '/feed',
        '/privacy', '/terms', '/sitemap',
    ];

    public function match(SeoProject $project): int
    {
        $entries = SeoSitemapEntry::where('seo_project_id', $project->id)
            ->whereNull('classified_at')
            ->whereNotNull('title')
            ->get();

        if ($entries->isEmpty()) {
            return 0;
        }

        $noiseIds = $entries->filter(fn (SeoSitemapEntry $entry) => $this->looksLikeNoise($entry->url))->pluck('id');

        if ($noiseIds->isNotEmpty()) {
            SeoSitemapEntry::whereIn('id', $noiseIds)->update(['classified_at' => now()]);
        }

        $candidates = $entries->whereNotIn('id', $noiseIds)->values();

        if ($candidates->isEmpty()) {
            return 0;
        }

        $provider = AiProvider::active();

        if (! $provider) {
            throw AiProviderException::noActiveProvider();
        }

        $clusterNames = SeoTopicCluster::where('seo_project_id', $project->id)->pluck('name')->all();
        $matchedCount = 0;

        foreach ($candidates->chunk(self::BATCH_SIZE) as $batch) {
            $results = $this->classifyBatch($provider, $project, $clusterNames, $batch);
            $matchedCount += $this->applyResults($project, $batch, $results);
        }

        return $matchedCount;
    }

    private function looksLikeNoise(string $url): bool
    {
        $path = mb_strtolower((string) parse_url($url, PHP_URL_PATH));

        foreach (self::NOISE_PATH_PATTERNS as $pattern) {
            if (str_contains($path, $pattern)) {
                return true;
            }
        }

        return $path === '' || $path === '/';
    }

    /**
     * @param  array<int, string>  $clusterNames
     * @return array<int, array{url: string, topic: ?string}>
     */
    private function classifyBatch(AiProvider $provider, SeoProject $project, array $clusterNames, Collection $batch): array
    {
        $clusterList = $clusterNames === [] ? '(هیچ‌کدام هنوز)' : implode('، ', $clusterNames);

        $pages = $batch->map(fn (SeoSitemapEntry $entry) => ['url' => $entry->url, 'title' => $entry->title])->values();

        $systemPrompt = <<<TXT
            You are a senior SEO analyst reviewing a business's own sitemap pages, to find which of the business's own content topics each page relates to.

            Business context:
            - Brand: {$project->brand}
            - Website: {$project->website_url}
            - Target market: {$project->market}
            - Real services/products (judge relevance ONLY against this list): {$project->real_services}

            This business's existing topic clusters (reuse one of these exact names whenever a page genuinely fits one - never invent a near-duplicate of an existing name):
            {$clusterList}

            For each page given (url + title), decide:
            - topic: if the page is genuine, substantive content for this business, either an EXACT existing cluster name above, or - only if it clearly doesn't fit any of them - a short new (2-4 word) Persian topic name. If the page is not real content (navigation, login, legal, an empty/generic listing), set topic to null.

            Respond with ONLY a JSON array, no markdown fences, no explanation, in exactly this shape:
            [{"url": "...", "topic": "..." | null}]
            TXT;

        $message = $provider->chatCompletion([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => json_encode($pages, JSON_UNESCAPED_UNICODE)],
        ]);

        return $this->parseJsonArray($message['content'] ?? '');
    }

    /**
     * @param  array<int, array{url: string, topic: ?string}>  $results
     */
    private function applyResults(SeoProject $project, Collection $batch, array $results): int
    {
        $byUrl = collect($results)->keyBy('url');
        $matchedCount = 0;

        foreach ($batch as $entry) {
            $result = $byUrl->get($entry->url);
            $topicClusterId = $this->resolveTopicCluster($project, $result['topic'] ?? null);

            $entry->update([
                'topic_cluster_id' => $topicClusterId,
                'classified_at' => now(),
            ]);

            if ($topicClusterId !== null) {
                $matchedCount++;
            }
        }

        return $matchedCount;
    }

    private function resolveTopicCluster(SeoProject $project, ?string $topicName): ?int
    {
        $topicName = trim((string) $topicName);

        if ($topicName === '') {
            return null;
        }

        return SeoTopicCluster::firstOrCreate(
            ['seo_project_id' => $project->id, 'name' => $topicName],
        )->id;
    }
}
