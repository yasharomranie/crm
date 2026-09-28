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
 */
class AiKeywordDiscoveryService
{
    use ParsesAiJsonArray;

    public function discover(SeoProject $project, int $count = 20): int
    {
        $provider = AiProvider::active();

        if (! $provider) {
            throw AiProviderException::noActiveProvider();
        }

        $existingKeywords = $project->keywordProfiles()->pluck('query')->all();
        $existingSuggestions = $project->keywordSuggestions()->pluck('keyword')->all();
        $topicNames = $project->topicClusters()->pluck('name')->all();

        $systemPrompt = <<<TXT
            You are a senior SEO strategist doing keyword discovery for one specific business - suggesting NEW keyword ideas it does not yet rank for or has not yet considered, never restating keywords it already has data for.

            Business context:
            - Brand: {$project->brand}
            - Website: {$project->website_url}
            - Primary business goal: {$project->goal->getLabel()}
            - Target market: {$project->market}
            - Audience: {$project->audience}

            Keywords already tracked (never suggest these again):
            {$this->formatList($existingKeywords)}

            Keywords already suggested previously (never repeat these either):
            {$this->formatList($existingSuggestions)}

            Existing topic clusters (reuse a name below when a new suggestion genuinely belongs to it, otherwise propose a new short Persian topic name):
            {$this->formatList($topicNames)}

            Suggest exactly {$count} new, realistic Persian search queries a real customer of this specific business would type into Google. For each, determine:
            - intent: exactly one of informational, commercial, transactional, navigational, local
            - topic: a short (2-4 word) Persian topic name (reuse an existing one above when it fits)
            - suggested_content_type: a short Persian label for what kind of page/content would target it (e.g. "مقاله وبلاگ", "صفحه فرود", "صفحه مقایسه", "صفحه سوالات متداول")
            - reason: one short Persian sentence explaining why this keyword matters for this specific business

            Respond with ONLY a JSON array, no markdown fences, no explanation, in exactly this shape:
            [{"keyword": "...", "intent": "...", "topic": "...", "suggested_content_type": "...", "reason": "..."}]
            TXT;

        $message = $provider->chatCompletion([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "لطفاً {$count} پیشنهاد کلمه کلیدی جدید تولید کن."],
        ]);

        $results = $this->parseJsonArray($message['content'] ?? '');

        return $this->storeSuggestions($project, $results);
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
