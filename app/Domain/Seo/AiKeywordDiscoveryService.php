<?php

namespace App\Domain\Seo;

use App\Domain\Seo\Concerns\ParsesAiJsonArray;
use App\Enums\SeoKeywordSuggestionStatus;
use App\Enums\SeoSearchIntent;
use App\Exceptions\AiProviderException;
use App\Models\AiProvider;
use App\Models\SeoKeywordSuggestion;
use App\Models\SeoProject;
use App\Models\SeoTopicCluster;

/**
 * "Keyword Discovery" (section 04 of the original spec): GSC can only ever
 * report queries the site *already* gets impressions for, so finding
 * genuinely new keyword ideas needs either a paid keyword-research API
 * (none is wired up yet) or the AI's own world knowledge. This uses the
 * latter, grounded in the project's business context and what it already
 * ranks for - and every suggestion is stored as an explicit hypothesis
 * (App\Models\SeoKeywordSuggestion, status "new") for a human to approve
 * or reject, never auto-added to the keyword profile table as if it were
 * observed data.
 *
 * Runs as two separate AI calls, not one - a lesson learned from an early
 * version that only forbade repeating *existing* keywords: nothing
 * stopped the model from generating two near-duplicate ideas within the
 * SAME batch (e.g. "مسیرهای دسترسی به غار علیصدر" and "راهنمای سفر به
 * غار علیصدر" in one nineteen-item run), since it never saw its own full
 * output at once while writing it.
 * 1. generate(): produce raw candidates, grounded in the project's real
 *    services (App\Models\SeoProject::real_services) and its full
 *    pillar/cluster map (App\Domain\Seo\PillarClusterMapBuilder) so it
 *    already avoids most overlap and irrelevance up front.
 * 2. review(): hand the *whole* raw batch back to the model as an editor
 *    pass - merge/drop internal near-duplicates, drop unnatural phrasing,
 *    drop anything outside real_services - before anything is stored.
 */
class AiKeywordDiscoveryService
{
    use ParsesAiJsonArray;

    public function __construct(
        private readonly PillarClusterMapBuilder $mapBuilder,
        private readonly CompetitorContentGapService $competitorGapService,
    ) {}

    public function discover(SeoProject $project, int $count = 20): int
    {
        $provider = AiProvider::active();

        if (! $provider) {
            throw AiProviderException::noActiveProvider();
        }

        $context = $this->businessContext($project);

        $raw = $this->generate($provider, $project, $context, $count);

        if ($raw === []) {
            return 0;
        }

        $reviewed = $this->review($provider, $context, $raw);

        return $this->storeSuggestions($project, $reviewed);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function generate(AiProvider $provider, SeoProject $project, string $context, int $count): array
    {
        $existingKeywords = $project->keywordProfiles()->pluck('query')->all();
        $existingSuggestions = $project->keywordSuggestions()->pluck('keyword')->all();

        $systemPrompt = <<<TXT
            You are a senior SEO strategist doing keyword discovery for one specific business - suggesting NEW keyword ideas it does not yet rank for or has not yet considered, never restating keywords it already has data for.

            {$context}

            Keywords already tracked (never suggest these again):
            {$this->formatList($existingKeywords)}

            Keywords already suggested previously (never repeat these either):
            {$this->formatList($existingSuggestions)}

            Suggest exactly {$count} new, realistic Persian search queries a real customer of this specific business would type into Google. Hard rules:
            - Each keyword must be phrased exactly as a real person would type it into Google - natural Persian word order, never an awkward or literally-translated phrase.
            - Do not suggest a keyword that only adds a minor variation to a topic cluster already well covered in the pillar/cluster map above - only suggest it if it adds a genuinely distinct angle.
            - Within this very batch you are about to produce, no two suggestions may target the same or a very close search intent - if two ideas are essentially the same, keep only the single best phrasing.
            - Only suggest keywords that plausibly relate to the business's real services/products listed above - never invent an unrelated service.

            For each suggestion determine:
            - intent: exactly one of informational, commercial, transactional, navigational, local
            - topic: a short (2-4 word) Persian topic name (reuse an existing cluster name from the map above when it genuinely fits)
            - suggested_content_type: a short Persian label for what kind of page/content would target it (e.g. "مقاله وبلاگ", "صفحه فرود", "صفحه مقایسه", "صفحه سوالات متداول")
            - reason: one short Persian sentence explaining why this keyword matters for this specific business

            Respond with ONLY a JSON array, no markdown fences, no explanation, in exactly this shape:
            [{"keyword": "...", "intent": "...", "topic": "...", "suggested_content_type": "...", "reason": "..."}]
            TXT;

        $message = $provider->chatCompletion([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "لطفاً {$count} پیشنهاد کلمه کلیدی جدید تولید کن."],
        ]);

        return $this->parseJsonArray($message['content'] ?? '');
    }

    /**
     * @param  array<int, array<string, mixed>>  $raw
     * @return array<int, array<string, mixed>>
     */
    private function review(AiProvider $provider, string $context, array $raw): array
    {
        $rawJson = json_encode($raw, JSON_UNESCAPED_UNICODE);

        $systemPrompt = <<<TXT
            You are a senior SEO editor reviewing a junior colleague's raw keyword-suggestion draft before it ships. Your job is to find and fix problems, not to invent new ideas.

            {$context}

            Raw draft to review:
            {$rawJson}

            For every item in the draft, check:
            1. Does it target the same or a very close search intent as another item in this same draft, or as an existing tracked/suggested keyword listed above? If so, keep only the single best, most complete phrasing and drop the rest - never keep both.
            2. Is it phrased exactly as a real person would type it into Google? If it is awkward or unnatural, fix the phrasing; if it cannot be fixed, drop it.
            3. Does it genuinely relate to the business's real services/products listed above? If not, drop it.
            4. Is it so broad and generic that a small business site has no realistic chance of ranking for it, with no business-specific angle at all? If so, either make it more specific to this business or drop it.

            Return ONLY the final, cleaned list - same JSON array shape as the input, nothing that fails any of the four checks above, no markdown fences, no explanation.
            TXT;

        $message = $provider->chatCompletion([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => 'لطفاً این پیش‌نویس را بازبینی و پالایش کن.'],
        ]);

        $reviewed = $this->parseJsonArray($message['content'] ?? '');

        // A review call that returned nothing parseable is a malformed
        // response, not "everything failed the checks" - falling back to
        // the raw draft is safer than silently discarding a whole batch.
        return $reviewed === [] ? $raw : $reviewed;
    }

    private function businessContext(SeoProject $project): string
    {
        $realServices = trim((string) $project->real_services);

        $servicesLine = $realServices !== ''
            ? $realServices
            : '(خالی - هشدار: این کسب‌وکار هنوز فهرست خدمات واقعی خودش را مشخص نکرده. فقط بر اساس نام برند و هدف کلی قضاوت کن و از حدس زدن خدمات خیلی خاص خودداری کن.)';

        return <<<TXT
            Business context:
            - Brand: {$project->brand}
            - Website: {$project->website_url}
            - Primary business goal: {$project->goal->getLabel()}
            - Target market: {$project->market}
            - Audience: {$project->audience}
            - Real services/products (judge relevance ONLY against this list, never invent a service not listed here): {$servicesLine}

            Current pillar/cluster map of this business's content and tracked keywords (shows what's already covered per topic, and where gaps are):
            {$this->formatPillarMap($project)}

            {$this->formatCompetitorCoverage($project)}
            TXT;
    }

    /**
     * What competitors (App\Models\SeoProjectCompetitor) are already
     * publishing, per topic cluster (App\Domain\Seo\
     * CompetitorContentGapService), next to how much of that same topic
     * this business itself already has - grounds a new suggestion in
     * real, observed market activity instead of only the model's own
     * general knowledge, and steers it toward topics competitors are
     * clearly active in but this business is not.
     */
    private function formatCompetitorCoverage(SeoProject $project): string
    {
        $coverage = $this->competitorGapService->coverageByCluster($project);

        if ($coverage->isEmpty()) {
            return '';
        }

        $myMap = $this->mapBuilder->build($project);

        $lines = $coverage->map(function (array $stats, int|string $clusterId) use ($myMap) {
            $cluster = SeoTopicCluster::find($clusterId);

            if (! $cluster) {
                return null;
            }

            $myGroup = $myMap->get($clusterId);
            $myContentCount = $myGroup ? $myGroup['contentItems']->count() : 0;

            return "- {$cluster->name}: {$stats['competitor_count']} رقیب با {$stats['page_count']} صفحه در مجموع، من: {$myContentCount} محتوای برنامه‌ریزی‌شده";
        })->filter()->implode("\n");

        if ($lines === '') {
            return '';
        }

        return <<<TXT
            What competitors are already publishing, per topic (from their public sitemaps - a real, observed signal, not a guess) - weigh this alongside the pillar map above, and favor topics competitors are active in but this business has little or no content for yet:
            {$lines}
            TXT;
    }

    private function formatPillarMap(SeoProject $project): string
    {
        $map = $this->mapBuilder->build($project);

        if ($map->isEmpty()) {
            return '(هنوز هیچ خوشه‌ی موضوعی یا کلمه‌ای برای این پروژه ثبت نشده است.)';
        }

        $lines = [];

        foreach ($map as $group) {
            $keywords = $group['profiles']->pluck('query')
                ->merge($group['suggestions']->pluck('keyword'))
                ->unique()
                ->values();

            if ($keywords->isEmpty()) {
                continue;
            }

            $lines[] = "- {$group['name']}: ".$keywords->implode('، ');
        }

        return $lines === [] ? '(هنوز هیچ خوشه‌ی موضوعی یا کلمه‌ای برای این پروژه ثبت نشده است.)' : implode("\n", $lines);
    }

    /**
     * @param  array<int, string>  $items
     */
    private function formatList(array $items): string
    {
        return $items === [] ? '(هیچ‌کدام)' : implode(', ', $items);
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     */
    private function storeSuggestions(SeoProject $project, array $results): int
    {
        $storedCount = 0;

        foreach ($results as $result) {
            $keyword = trim((string) ($result['keyword'] ?? ''));

            if ($keyword === '') {
                continue;
            }

            $topicClusterId = null;
            $topicName = trim((string) ($result['topic'] ?? ''));

            if ($topicName !== '') {
                $topicClusterId = SeoTopicCluster::firstOrCreate(
                    ['seo_project_id' => $project->id, 'name' => $topicName],
                )->id;
            }

            SeoKeywordSuggestion::create([
                'seo_project_id' => $project->id,
                'keyword' => $keyword,
                'intent' => SeoSearchIntent::tryFrom($result['intent'] ?? ''),
                'topic_cluster_id' => $topicClusterId,
                'suggested_content_type' => $result['suggested_content_type'] ?? null,
                'reason' => $result['reason'] ?? null,
                'status' => SeoKeywordSuggestionStatus::New,
            ]);

            $storedCount++;
        }

        return $storedCount;
    }
}
