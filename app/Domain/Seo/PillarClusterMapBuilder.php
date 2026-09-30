<?php

namespace App\Domain\Seo;

use App\Models\SeoContentItem;
use App\Models\SeoKeywordProfile;
use App\Models\SeoKeywordSuggestion;
use App\Models\SeoProject;
use App\Models\SeoTopicCluster;
use Illuminate\Support\Collection;

/**
 * Groups everything a project has under each of its topic clusters
 * ("pillars") - real tracked keywords (App\Models\SeoKeywordProfile),
 * AI-suggested keywords (App\Models\SeoKeywordSuggestion), and content
 * items - so an SEO operator can see the whole content map for one
 * cluster at once, not scattered across three separate resource tables.
 *
 * Deliberately does NOT reinvent cannibalization detection for keywords
 * that already have real Search Console data - App\Domain\Seo\
 * OpportunityEngine::detectCannibalization() already does that properly,
 * from actual click/impression splits across pages, and its verdict is
 * exposed here as-is via SeoKeywordProfile::cannibalization_risk. This
 * class only adds a second, much cruder signal for keywords that have
 * NO real traffic data yet (fresh AI suggestions, or a suggestion next
 * to an existing tracked keyword) where the real detector has nothing to
 * measure: a plain lexical-overlap check across the words in a cluster's
 * keyword texts, after stripping words so common ACROSS the project's
 * different clusters that they'd otherwise drown every real signal (a
 * project about "همدان" has that word in nearly every cluster - a raw
 * word-overlap check would flag almost everything as "similar").
 *
 * Stopword frequency is counted per DISTINCT CLUSTER a word appears in,
 * never per individual keyword - a word repeated across every keyword
 * WITHIN one cluster is expected (that repetition is what makes it a
 * cluster), so counting it that way would strip a cluster's own
 * defining topic (e.g. "غار علیصدر") the moment a project has only
 * that one cluster so far, which is exactly backwards.
 */
class PillarClusterMapBuilder
{
    private const SIMILARITY_THRESHOLD = 0.5;

    /**
     * A word present in more than this share of a project's distinct
     * clusters is treated as a project-wide "brand/location" word rather
     * than a distinguishing topic word.
     */
    private const CORPUS_STOPWORD_SHARE = 0.5;

    /**
     * Below this many clusters, frequency stats aren't meaningful enough
     * to safely strip anything.
     */
    private const MIN_CLUSTERS_FOR_STOPWORDS = 3;

    private const PERSIAN_STOPWORDS = [
        'در', 'به', 'از', 'با', 'برای', 'و', 'یا', 'که', 'را', 'این', 'آن',
        'های', 'ها', 'چه', 'چی', 'کجا', 'کی', 'چگونه', 'بهترین', 'بهتر',
    ];

    /**
     * @return Collection<int|string, array{name: string, profiles: Collection<int, SeoKeywordProfile>, suggestions: Collection<int, SeoKeywordSuggestion>, contentItems: Collection<int, SeoContentItem>, warnings: array<int, array{a: string, b: string, similarity: float}>}>
     */
    public function build(SeoProject $project): Collection
    {
        $clusters = SeoTopicCluster::where('seo_project_id', $project->id)->get()->keyBy('id');

        $profiles = SeoKeywordProfile::where('seo_project_id', $project->id)->get();
        $suggestions = SeoKeywordSuggestion::where('seo_project_id', $project->id)->get();
        $contentItems = SeoContentItem::where('seo_project_id', $project->id)
            ->with(['keywordProfile', 'keywordSuggestion'])
            ->get();

        $groups = collect();

        $groupFor = function (int|string $key) use (&$groups, $clusters): array {
            return $groups[$key] ??= [
                'name' => $key === 'uncategorized' ? 'دسته‌بندی‌نشده' : $clusters[$key]->name,
                'profiles' => collect(),
                'suggestions' => collect(),
                'contentItems' => collect(),
                'warnings' => [],
            ];
        };

        foreach ($profiles as $profile) {
            $key = $profile->topic_cluster_id ?? 'uncategorized';
            $group = $groupFor($key);
            $group['profiles']->push($profile);
            $groups[$key] = $group;
        }

        foreach ($suggestions as $suggestion) {
            $key = $suggestion->topic_cluster_id ?? 'uncategorized';
            $group = $groupFor($key);
            $group['suggestions']->push($suggestion);
            $groups[$key] = $group;
        }

        foreach ($contentItems as $item) {
            $key = $item->keywordProfile?->topic_cluster_id
                ?? $item->keywordSuggestion?->topic_cluster_id
                ?? 'uncategorized';
            $group = $groupFor($key);
            $group['contentItems']->push($item);
            $groups[$key] = $group;
        }

        $corpusStopwords = $this->crossClusterStopwords($groups);

        foreach ($groups as $key => $group) {
            $texts = $group['profiles']->pluck('query')->merge($group['suggestions']->pluck('keyword'))->unique()->values();
            $group['warnings'] = $this->detectOverlaps($texts, $corpusStopwords);
            $groups[$key] = $group;
        }

        return $groups;
    }

    /**
     * @param  Collection<int|string, array{profiles: Collection<int, SeoKeywordProfile>, suggestions: Collection<int, SeoKeywordSuggestion>}>  $groups
     * @return array<string, int>
     */
    private function crossClusterStopwords(Collection $groups): array
    {
        $clusterCount = $groups->count();

        if ($clusterCount < self::MIN_CLUSTERS_FOR_STOPWORDS) {
            return [];
        }

        $clusterDocumentCount = [];

        foreach ($groups as $group) {
            $texts = $group['profiles']->pluck('query')->merge($group['suggestions']->pluck('keyword'));
            $wordsInThisCluster = [];

            foreach ($texts as $text) {
                foreach ($this->words($text) as $word) {
                    $wordsInThisCluster[$word] = true;
                }
            }

            foreach (array_keys($wordsInThisCluster) as $word) {
                $clusterDocumentCount[$word] = ($clusterDocumentCount[$word] ?? 0) + 1;
            }
        }

        $threshold = $clusterCount * self::CORPUS_STOPWORD_SHARE;

        return array_filter($clusterDocumentCount, fn (int $count) => $count > $threshold);
    }

    /**
     * @param  Collection<int, string>  $texts
     * @param  array<string, int>  $corpusStopwords
     * @return array<int, array{a: string, b: string, similarity: float}>
     */
    private function detectOverlaps(Collection $texts, array $corpusStopwords): array
    {
        $significantWords = $texts->mapWithKeys(fn (string $text) => [$text => $this->significantWords($text, $corpusStopwords)]);

        $warnings = [];
        $values = $texts->values();

        for ($i = 0; $i < $values->count(); $i++) {
            for ($j = $i + 1; $j < $values->count(); $j++) {
                $a = $values[$i];
                $b = $values[$j];
                $similarity = $this->overlapCoefficient($significantWords[$a], $significantWords[$b]);

                if ($similarity >= self::SIMILARITY_THRESHOLD) {
                    $warnings[] = ['a' => $a, 'b' => $b, 'similarity' => round($similarity, 2)];
                }
            }
        }

        return $warnings;
    }

    /**
     * @param  array<string, int>  $corpusStopwords
     * @return array<int, string>
     */
    private function significantWords(string $text, array $corpusStopwords): array
    {
        return array_values(array_diff(
            $this->words($text),
            self::PERSIAN_STOPWORDS,
            array_keys($corpusStopwords),
        ));
    }

    /**
     * @return array<int, string>
     */
    private function words(string $text): array
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];

        return array_values(array_filter(array_map(fn (string $word) => mb_strtolower($word), $words)));
    }

    /**
     * The overlap (Szymkiewicz-Simpson) coefficient - intersection over
     * the SMALLER set's size, not the union's (plain Jaccard). Two
     * keywords built around the same core phrase (e.g. a shared proper
     * noun like "غار علیصدر") but of different lengths still score high
     * this way; Jaccard would dilute that same overlap toward zero as
     * soon as either phrase adds a few unrelated words, which is exactly
     * the case a cannibalization warning most needs to catch.
     *
     * @param  array<int, string>  $a
     * @param  array<int, string>  $b
     */
    private function overlapCoefficient(array $a, array $b): float
    {
        if ($a === [] || $b === []) {
            return 0.0;
        }

        $intersection = count(array_intersect($a, $b));
        $smaller = min(count($a), count($b));

        return $smaller === 0 ? 0.0 : $intersection / $smaller;
    }
}
