<?php

namespace App\Domain\Seo;

use App\Domain\Seo\Concerns\ParsesAiJsonArray;
use App\Enums\SeoBusinessValue;
use App\Enums\SeoSearchIntent;
use App\Exceptions\AiProviderException;
use App\Models\AiProvider;
use App\Models\SeoKeywordProfile;
use App\Models\SeoProject;
use App\Models\SeoTopicCluster;
use Illuminate\Support\Collection;

/**
 * Fills in the three fields KeywordIntelligenceService deliberately leaves
 * null: intent, business_value, topic_cluster_id. These need semantic
 * judgment a plain aggregation can't honestly provide - so this asks the
 * project's active AiProvider (the same GapGPT/OpenAI-compatible gateway
 * AiAssistant already uses, see AiProvider::chatCompletion()), grounded in
 * the project's own business context, never a generic classifier blind to
 * what the business actually sells.
 *
 * Every AI call returns structured JSON (never freeform prose) so results
 * are parseable and this stays auditable - a keyword the model didn't
 * return, or returned in a shape that doesn't parse, is simply left
 * unclassified (classified_at stays null) rather than guessed at, and
 * picked up again on the next run.
 */
class AiKeywordClassifier
{
    use ParsesAiJsonArray;

    private const BATCH_SIZE = 20;

    public function classify(SeoProject $project): int
    {
        $provider = AiProvider::active();

        if (! $provider) {
            throw AiProviderException::noActiveProvider();
        }

        $profiles = SeoKeywordProfile::where('seo_project_id', $project->id)
            ->whereNull('classified_at')
            ->orderByDesc('impressions_28d')
            ->get();

        $classifiedCount = 0;

        foreach ($profiles->chunk(self::BATCH_SIZE) as $batch) {
            $results = $this->classifyBatch($provider, $project, $batch);
            $classifiedCount += $this->applyResults($project, $batch, $results);
        }

        return $classifiedCount;
    }

    /**
     * @return array<int, array{keyword: string, intent?: string, business_value?: string, topic?: string}>
     */
    private function classifyBatch(AiProvider $provider, SeoProject $project, Collection $batch): array
    {
        $systemPrompt = <<<TXT
            You are a senior SEO analyst classifying search queries for one specific business. Never treat these queries as generic web searches - always weigh them against this exact business.

            Business context:
            - Brand: {$project->brand}
            - Website: {$project->website_url}
            - Primary business goal: {$project->goal->getLabel()}
            - Target market: {$project->market}
            - Audience: {$project->audience}

            For each keyword given, determine:
            - intent: exactly one of informational, commercial, transactional, navigational, local
            - business_value: exactly one of high, medium, low - how valuable this keyword is toward the stated business goal specifically, NOT how much search traffic it gets
            - topic: a short (2-4 word) Persian topic/theme name this keyword belongs to, so related keywords can be grouped under the same topic later

            Respond with ONLY a JSON array, no markdown fences, no explanation, in exactly this shape:
            [{"keyword": "...", "intent": "...", "business_value": "...", "topic": "..."}]
            TXT;

        $message = $provider->chatCompletion([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => json_encode($batch->pluck('query')->values(), JSON_UNESCAPED_UNICODE)],
        ]);

        return $this->parseJsonArray($message['content'] ?? '');
    }

    private function applyResults(SeoProject $project, Collection $batch, array $results): int
    {
        $byKeyword = collect($results)->keyBy('keyword');
        $appliedCount = 0;

        foreach ($batch as $profile) {
            $result = $byKeyword->get($profile->query);

            if (! $result) {
                continue;
            }

            $intent = SeoSearchIntent::tryFrom($result['intent'] ?? '');
            $businessValue = SeoBusinessValue::tryFrom($result['business_value'] ?? '');
            $topicClusterId = $this->resolveTopicCluster($project, $result['topic'] ?? null);

            $profile->update([
                'intent' => $intent,
                'business_value' => $businessValue,
                'topic_cluster_id' => $topicClusterId,
                'classified_at' => now(),
            ]);

            $appliedCount++;
        }

        return $appliedCount;
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
