<?php

namespace App\Domain\Seo;

use App\Enums\SeoKeywordSuggestionStatus;
use App\Models\SeoKeywordProfile;
use App\Models\SeoKeywordSuggestion;
use App\Models\SeoProject;

/**
 * The "what to write" half that App\Domain\Seo\ContentCalendarPlannerService
 * deliberately left out (it only ever schedules an *existing* backlog - see
 * its own doc comment). This is a pure data signal, not an AI call: Search
 * Console already proves real demand for a query the moment it has
 * meaningful impressions, whether or not the site ranks well for it yet -
 * so a profile with real impressions but no content item deliberately
 * targeting it is a genuine, demonstrated gap, not a guess.
 *
 * Deliberately NOT a competitor content-gap analysis (comparing against
 * what other sites rank for) - that needs a paid third-party keyword/SERP
 * API this project doesn't have wired up. App\Domain\Seo\
 * AiKeywordDiscoveryService already covers the adjacent "genuinely new
 * idea the business hasn't considered yet" case using the AI's own world
 * knowledge; this instead only ever surfaces queries the project's own
 * data already proves matter.
 *
 * Every gap found is stored as an ordinary App\Models\SeoKeywordSuggestion
 * (status New) - the same human-approval pipeline AI-discovered keywords
 * already go through - rather than a new table or a separate approval
 * flow.
 */
class ContentGapService
{
    /**
     * Below this many impressions in the last 28 days, a query's "demand"
     * is too thin to call a deliberate gap - matches the same ballpark as
     * OpportunityEngine's other impression-based thresholds (20-100).
     */
    private const MIN_IMPRESSIONS = 30;

    public function detect(SeoProject $project): int
    {
        $gaps = SeoKeywordProfile::where('seo_project_id', $project->id)
            ->where('impressions_28d', '>=', self::MIN_IMPRESSIONS)
            ->whereDoesntHave('contentItems')
            ->get();

        if ($gaps->isEmpty()) {
            return 0;
        }

        // Keyed by normalized text so a gap already turned into a
        // suggestion (by this or an earlier run) is never suggested again,
        // even once its profile stops showing up in the whereDoesntHave()
        // check above (e.g. the resulting content item only ever gets
        // linked via keyword_suggestion_id, not keyword_profile_id).
        $existing = SeoKeywordSuggestion::where('seo_project_id', $project->id)
            ->pluck('keyword')
            ->map(fn (string $keyword) => $this->normalize($keyword))
            ->flip();

        $created = 0;

        foreach ($gaps as $profile) {
            $normalized = $this->normalize($profile->query);

            if (isset($existing[$normalized])) {
                continue;
            }

            SeoKeywordSuggestion::create([
                'seo_project_id' => $project->id,
                'keyword' => $profile->query,
                'intent' => $profile->intent,
                'topic_cluster_id' => $profile->topic_cluster_id,
                'reason' => "شکاف محتوایی: این عبارت در ۲۸ روز اخیر {$profile->impressions_28d} بار در نتایج گوگل نمایش داده شده، اما هیچ محتوایی برایش هدف‌گذاری نشده است.",
                'status' => SeoKeywordSuggestionStatus::New,
            ]);

            $existing[$normalized] = true;
            $created++;
        }

        return $created;
    }

    private function normalize(string $keyword): string
    {
        return mb_strtolower(trim($keyword));
    }
}
