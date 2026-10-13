<?php

namespace App\Domain\Seo;

use App\Enums\SeoKeywordSuggestionStatus;
use App\Enums\SeoOpportunityStatus;
use App\Enums\SeoTechnicalAuditStatus;
use App\Models\SeoKeywordSuggestion;
use App\Models\SeoOpportunity;
use App\Models\SeoProject;
use App\Models\SeoTechnicalAudit;
use App\Models\SeoTechnicalAuditIssue;
use Illuminate\Support\Collection;

/**
 * Merges every open finding across مرکز سئو's otherwise-separate modules
 * (keyword/URL opportunities, technical audit issues, and pending keyword
 * suggestions - whether AI-discovered or gap-detected, see
 * App\Domain\Seo\AiKeywordDiscoveryService and
 * App\Domain\Seo\CompetitorContentGapService) into one priority-ordered
 * list, so a project owner sees "do these N things" in one place instead
 * of hunting through N different resource tables.
 *
 * Deliberately a read-time aggregation, not a new persisted table: each
 * source already owns its own lifecycle (an opportunity's own status, an
 * issue's presence in the latest technical audit, a suggestion's own
 * status), and duplicating that into a second table would only risk the
 * two drifting out of sync.
 */
class SeoActionQueueService
{
    /**
     * @var array<string, float>
     */
    private const OPPORTUNITY_PRIORITY_WEIGHT = [
        'high' => 3.0,
        'medium' => 2.0,
        'low' => 1.0,
    ];

    /**
     * @var array<string, float>
     */
    private const CONFIDENCE_MULTIPLIER = [
        'high' => 1.0,
        'medium' => 0.7,
        'low' => 0.4,
    ];

    /**
     * @var array<string, float>
     */
    private const ISSUE_SEVERITY_WEIGHT = [
        'critical' => 3.0,
        'warning' => 2.0,
        'info' => 1.0,
    ];

    /**
     * A pending suggestion has no priority/confidence of its own (unlike
     * SeoOpportunity) - it's weighted like a medium-priority opportunity,
     * since it's already a concrete idea مرکز سئو thinks worth acting on,
     * not just a detected anomaly still needing a human to judge it.
     */
    private const KEYWORD_SUGGESTION_WEIGHT = 2.0;

    /**
     * @return Collection<int, SeoActionItem>
     */
    public function items(SeoProject $project): Collection
    {
        return $this->opportunityItems($project)
            ->merge($this->technicalIssueItems($project))
            ->merge($this->keywordSuggestionItems($project))
            ->sortByDesc(fn (SeoActionItem $item) => $item->score)
            ->values();
    }

    /**
     * @return Collection<int, SeoActionItem>
     */
    private function opportunityItems(SeoProject $project): Collection
    {
        // Wrapped in collect() rather than mapping the Eloquent collection
        // directly - an *empty* Eloquent collection stays an Eloquent
        // collection through ->map() (nothing non-Model to trigger its
        // own downgrade-to-base-Collection check), and merging that with
        // a plain Collection of SeoActionItem below would crash looking
        // for a primary key none of them have.
        return collect(SeoOpportunity::where('seo_project_id', $project->id)
            ->whereIn('status', [SeoOpportunityStatus::Detected, SeoOpportunityStatus::Reviewed])
            ->get())
            ->map(fn (SeoOpportunity $opportunity) => new SeoActionItem(
                source: 'opportunity',
                title: $opportunity->title,
                detail: $opportunity->subject_query ?? $opportunity->subject_page_url,
                url: $opportunity->subject_page_url,
                score: (self::OPPORTUNITY_PRIORITY_WEIGHT[$opportunity->priority->value] ?? 1.0)
                    * (self::CONFIDENCE_MULTIPLIER[$opportunity->confidence->value] ?? 1.0),
                detectedAt: $opportunity->detected_at,
            ));
    }

    /**
     * Only the latest *completed* audit's issues - an issue that no longer
     * shows up in a newer audit is treated as resolved without needing a
     * status column on SeoTechnicalAuditIssue itself; the next weekly
     * crawl (seo:run-technical-audits) is what actually re-measures it.
     *
     * @return Collection<int, SeoActionItem>
     */
    private function technicalIssueItems(SeoProject $project): Collection
    {
        $latestAudit = SeoTechnicalAudit::where('seo_project_id', $project->id)
            ->where('status', SeoTechnicalAuditStatus::Completed)
            ->orderByDesc('completed_at')
            ->first();

        if (! $latestAudit) {
            return collect();
        }

        return collect($latestAudit->issues()->get())->map(fn (SeoTechnicalAuditIssue $issue) => new SeoActionItem(
            source: 'technical_issue',
            title: $issue->type->getLabel(),
            detail: $issue->details,
            url: $issue->url,
            score: self::ISSUE_SEVERITY_WEIGHT[$issue->severity->value] ?? 1.0,
            detectedAt: $latestAudit->completed_at,
        ));
    }

    /**
     * @return Collection<int, SeoActionItem>
     */
    private function keywordSuggestionItems(SeoProject $project): Collection
    {
        return collect(SeoKeywordSuggestion::where('seo_project_id', $project->id)
            ->where('status', SeoKeywordSuggestionStatus::New)
            ->get())
            ->map(fn (SeoKeywordSuggestion $suggestion) => new SeoActionItem(
                source: 'keyword_suggestion',
                title: $suggestion->keyword,
                detail: $suggestion->reason,
                url: null,
                score: self::KEYWORD_SUGGESTION_WEIGHT,
                detectedAt: $suggestion->created_at,
            ));
    }
}
