<?php

namespace App\Domain\Seo;

use App\Enums\SeoOpportunityOutcomeVerdict;
use App\Models\SeoDailyMetric;
use App\Models\SeoOpportunity;
use App\Models\SeoOpportunityOutcome;
use App\Models\SeoQueryMetric;
use Illuminate\Support\Carbon;

/**
 * The "learning" step of مرکز سئو's data -> analysis -> opportunity ->
 * action -> measurement -> learning loop: instead of assuming a resolved
 * opportunity actually helped, this checks. captureBaseline() snapshots
 * the trailing window's clicks/impressions/position the moment an
 * opportunity is marked resolved; evaluate() re-measures the same window
 * once enough time has passed and records whether it moved.
 *
 * Measures via the opportunity's own subject_query (SeoQueryMetric) when
 * one is set - CtrOpportunity and Cannibalization both have one - or the
 * whole project's daily clicks (SeoDailyMetric) for project-wide types
 * like TrafficDrop/AnomalyNegative, so every opportunity type is
 * measurable the same way.
 */
class OpportunityOutcomeService
{
    /**
     * Long enough for a real trend, not day-to-day noise - matches the
     * same trailing-28-day convention KeywordIntelligenceService and
     * OpportunityEngine already use elsewhere in this warehouse.
     */
    public const FOLLOWUP_WINDOW_DAYS = 28;

    /** A click-volume change smaller than this is noise, not a real result. */
    private const IMPROVEMENT_THRESHOLD = 0.15;

    public function captureBaseline(SeoOpportunity $opportunity): SeoOpportunityOutcome
    {
        $metrics = $this->metricsSince($opportunity, now()->subDays(self::FOLLOWUP_WINDOW_DAYS), now());

        return SeoOpportunityOutcome::updateOrCreate(
            ['seo_opportunity_id' => $opportunity->id],
            [
                'baseline_captured_at' => now(),
                'baseline_clicks' => $metrics['clicks'],
                'baseline_impressions' => $metrics['impressions'],
                'baseline_position' => $metrics['avg_position'],
                'followup_measured_at' => null,
                'followup_clicks' => null,
                'followup_impressions' => null,
                'followup_position' => null,
                'verdict' => SeoOpportunityOutcomeVerdict::Pending,
            ],
        );
    }

    public function evaluate(SeoOpportunityOutcome $outcome): void
    {
        $opportunity = $outcome->opportunity;
        $metrics = $this->metricsSince($opportunity, now()->subDays(self::FOLLOWUP_WINDOW_DAYS), now());

        $relativeChange = $outcome->baseline_clicks > 0
            ? ($metrics['clicks'] - $outcome->baseline_clicks) / $outcome->baseline_clicks
            : ($metrics['clicks'] > 0 ? 1.0 : 0.0);

        $verdict = match (true) {
            $relativeChange >= self::IMPROVEMENT_THRESHOLD => SeoOpportunityOutcomeVerdict::Improved,
            $relativeChange <= -self::IMPROVEMENT_THRESHOLD => SeoOpportunityOutcomeVerdict::Declined,
            default => SeoOpportunityOutcomeVerdict::Unchanged,
        };

        $outcome->update([
            'followup_measured_at' => now(),
            'followup_clicks' => $metrics['clicks'],
            'followup_impressions' => $metrics['impressions'],
            'followup_position' => $metrics['avg_position'],
            'verdict' => $verdict,
        ]);
    }

    /**
     * @return array{clicks: int, impressions: int, avg_position: ?float}
     */
    private function metricsSince(SeoOpportunity $opportunity, Carbon $start, Carbon $end): array
    {
        $query = filled($opportunity->subject_query)
            ? SeoQueryMetric::query()
                ->where('seo_project_id', $opportunity->seo_project_id)
                ->where('query_hash', sha1($opportunity->subject_query))
            : SeoDailyMetric::query()
                ->where('seo_project_id', $opportunity->seo_project_id);

        $row = $query
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->selectRaw('coalesce(sum(clicks), 0) as clicks, coalesce(sum(impressions), 0) as impressions, avg(position) as avg_position')
            ->first();

        return [
            'clicks' => (int) $row->clicks,
            'impressions' => (int) $row->impressions,
            'avg_position' => $row->avg_position !== null ? round((float) $row->avg_position, 2) : null,
        ];
    }
}
