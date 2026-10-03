<?php

namespace App\Domain\Seo;

use App\Enums\SeoOpportunityOutcomeVerdict;
use App\Enums\SeoTechnicalAuditStatus;
use App\Models\SeoContentItem;
use App\Models\SeoDailyMetric;
use App\Models\SeoOpportunity;
use App\Models\SeoOpportunityOutcome;
use App\Models\SeoPeriodicReport;
use App\Models\SeoProject;
use App\Models\SeoTechnicalAudit;
use App\Models\SeoTrackedKeyword;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Builds one project's periodic executive summary - ties together every
 * phase already in مرکز سئو (search performance, opportunities, content,
 * rank tracker, technical audits) into a single print-ready page, the
 * same house convention as resources/views/reports/print.blade.php: no
 * PDF library, the stored HTML is rendered once at generation time so the
 * numbers stay exactly what they were that period.
 */
class PeriodicReportService
{
    /**
     * GSC typically has no finalized data for the most recent few days yet
     * - matches SyncSearchConsoleDataJob::REPORTING_LAG_DAYS. A report must
     * never compare a partial, still-arriving period against a complete
     * one, so every date-bound section below is clamped to this horizon.
     */
    private const REPORTING_LAG_DAYS = 3;

    public function generate(SeoProject $project, Carbon $periodStart, Carbon $periodEnd): SeoPeriodicReport
    {
        $effectiveEnd = $this->clampToAvailableData($periodEnd);

        $search = $this->searchPerformance($project, $periodStart, $effectiveEnd);
        $opportunities = $this->opportunitySummary($project, $periodStart, $periodEnd);
        $actionItems = app(SeoActionQueueService::class)->items($project);

        $data = [
            'project' => $project,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'dataAvailableThrough' => $effectiveEnd->isSameDay($periodEnd) ? null : $effectiveEnd,
            'search' => $search,
            'opportunities' => $opportunities,
            'contentPublishedCount' => SeoContentItem::where('seo_project_id', $project->id)
                ->whereBetween('published_at', [$periodStart->clone()->startOfDay(), $periodEnd->clone()->endOfDay()])
                ->count(),
            'rankHighlights' => $this->rankHighlights($project, $periodStart, $periodEnd),
            'technicalAudit' => SeoTechnicalAudit::where('seo_project_id', $project->id)
                ->where('status', SeoTechnicalAuditStatus::Completed->value)
                ->where('completed_at', '<=', $periodEnd->clone()->endOfDay())
                ->orderByDesc('completed_at')
                ->first(),
            'narrative' => $this->narrative($project, $search, $opportunities, $actionItems),
            'dailySeries' => $this->dailySeries($project, $periodStart, $effectiveEnd),
        ];

        $html = view('seo.periodic-report', $data)->render();

        return SeoPeriodicReport::create([
            'seo_project_id' => $project->id,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'html' => $html,
            'generated_at' => now(),
        ]);
    }

    /**
     * A requested end date that reaches into GSC's own reporting-lag
     * window is pulled back to the last day Google could possibly have
     * data for - never silently left as-is, which would make the current
     * period partial while any comparison period stays complete.
     */
    private function clampToAvailableData(Carbon $end): Carbon
    {
        $lastAvailable = now()->subDays(self::REPORTING_LAG_DAYS)->startOfDay();

        return $end->greaterThan($lastAvailable) ? $lastAvailable : $end->clone();
    }

    /**
     * @return array{clicks: int, impressions: int, avg_position: ?float, previous_clicks: int, percent_change: ?float}
     */
    private function searchPerformance(SeoProject $project, Carbon $start, Carbon $end): array
    {
        $periodDays = $start->diffInDays($end) + 1;
        $previousStart = $start->clone()->subDays($periodDays);
        $previousEnd = $start->clone()->subDay();

        $this->refreshDailyMetrics($project, $previousStart, $end);

        $current = SeoDailyMetric::where('seo_project_id', $project->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('sum(clicks) as clicks, sum(impressions) as impressions, avg(position) as avg_position')
            ->first();

        $previousClicks = (int) SeoDailyMetric::where('seo_project_id', $project->id)
            ->whereBetween('date', [$previousStart->toDateString(), $previousEnd->toDateString()])
            ->sum('clicks');

        $clicks = (int) ($current->clicks ?? 0);
        $percentChange = $previousClicks > 0 ? round((($clicks - $previousClicks) / $previousClicks) * 100, 1) : null;

        return [
            'clicks' => $clicks,
            'impressions' => (int) ($current->impressions ?? 0),
            'avg_position' => $current->avg_position !== null ? round((float) $current->avg_position, 1) : null,
            'previous_clicks' => $previousClicks,
            'percent_change' => $percentChange,
        ];
    }

    /**
     * Pulls this exact date range fresh from the Search Console API - not
     * whatever happens to already be sitting in the warehouse - before the
     * report reads it, so a report is never built on stale or missing days
     * just because the nightly sync hasn't run recently. Scoped to the
     * ['date'] dimension only (the one thing this report needs), unlike
     * SyncSearchConsoleDataJob's full ~20-call sync, so this stays cheap
     * enough to run inline during report generation. Best-effort: with no
     * active Google connection, or if the live call fails, the report
     * simply falls back to whatever is already stored (unchanged from
     * this service's original behavior).
     */
    private function refreshDailyMetrics(SeoProject $project, Carbon $start, Carbon $end): void
    {
        $connection = $project->googleConnection;

        if (! $connection || blank($connection->gsc_site_url)) {
            return;
        }

        try {
            $accessToken = app(GoogleOAuthService::class)->freshAccessTokenFor($connection);
            $rows = (new SearchConsoleClient($accessToken))
                ->querySearchAnalytics($connection->gsc_site_url, $start->toDateString(), $end->toDateString(), ['date']);

            if (empty($rows)) {
                return;
            }

            $now = now();

            SeoDailyMetric::query()->upsert(
                collect($rows)->map(fn (array $row) => [
                    'seo_project_id' => $project->id,
                    'date' => $row['keys'][0],
                    'clicks' => (int) ($row['clicks'] ?? 0),
                    'impressions' => (int) ($row['impressions'] ?? 0),
                    'ctr' => (float) ($row['ctr'] ?? 0),
                    'position' => (float) ($row['position'] ?? 0),
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all(),
                ['seo_project_id', 'date'],
                ['clicks', 'impressions', 'ctr', 'position', 'updated_at'],
            );
        } catch (\Throwable $exception) {
            Log::warning('PeriodicReportService: live GSC refresh failed; falling back to stored metrics.', [
                'seo_project_id' => $project->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array{detected: int, resolved: int, improved: int, unchanged: int, declined: int}
     */
    private function opportunitySummary(SeoProject $project, Carbon $start, Carbon $end): array
    {
        $range = [$start->clone()->startOfDay(), $end->clone()->endOfDay()];

        $detected = SeoOpportunity::where('seo_project_id', $project->id)
            ->whereBetween('detected_at', $range)
            ->count();

        $resolved = SeoOpportunity::where('seo_project_id', $project->id)
            ->whereBetween('resolved_at', $range)
            ->count();

        $verdicts = SeoOpportunityOutcome::whereHas('opportunity', fn ($query) => $query->where('seo_project_id', $project->id))
            ->whereBetween('followup_measured_at', $range)
            ->get()
            ->countBy(fn (SeoOpportunityOutcome $outcome) => $outcome->verdict->value);

        return [
            'detected' => $detected,
            'resolved' => $resolved,
            'improved' => $verdicts->get(SeoOpportunityOutcomeVerdict::Improved->value) ?? 0,
            'unchanged' => $verdicts->get(SeoOpportunityOutcomeVerdict::Unchanged->value) ?? 0,
            'declined' => $verdicts->get(SeoOpportunityOutcomeVerdict::Declined->value) ?? 0,
        ];
    }

    /**
     * Compares each tracked keyword's earliest vs latest position *within
     * the period* - not against some baseline from before it - so the
     * report only ever claims to describe what happened during the
     * period itself.
     *
     * @return Collection<int, array{keyword: string, from: ?int, to: ?int, delta: ?int}>
     */
    private function rankHighlights(SeoProject $project, Carbon $start, Carbon $end): Collection
    {
        return SeoTrackedKeyword::where('seo_project_id', $project->id)
            ->with(['rankChecks' => fn ($query) => $query->whereBetween('date', [$start->toDateString(), $end->toDateString()])->orderBy('date')])
            ->get()
            ->map(function (SeoTrackedKeyword $keyword) {
                $checks = $keyword->rankChecks;

                if ($checks->isEmpty()) {
                    return null;
                }

                $from = $checks->first()->position;
                $to = $checks->last()->position;

                return [
                    'keyword' => $keyword->keyword,
                    'from' => $from,
                    'to' => $to,
                    'delta' => ($from !== null && $to !== null) ? $from - $to : null,
                ];
            })
            ->filter()
            ->sortByDesc(fn ($row) => $row['delta'] ?? -100)
            ->values();
    }

    /**
     * Best-effort, same reasoning as TechnicalSeoAuditor's own PageSpeed
     * check: a missing/failed AI provider must never break a report that
     * already has useful numbers without it - the narrative section is
     * just left out.
     *
     * @param  array{clicks: int, impressions: int, avg_position: ?float, previous_clicks: int, percent_change: ?float}  $search
     * @param  array{detected: int, resolved: int, improved: int, unchanged: int, declined: int}  $opportunities
     * @param  Collection<int, SeoActionItem>  $actionItems
     */
    private function narrative(SeoProject $project, array $search, array $opportunities, Collection $actionItems): ?string
    {
        try {
            return app(SeoAiAnalystService::class)->narrativeFor($project, $search, $opportunities, $actionItems);
        } catch (\Throwable $exception) {
            Log::warning('PeriodicReportService: AI narrative failed.', [
                'seo_project_id' => $project->id,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * The raw series the report's daily-clicks chart draws - baked into
     * the stored HTML at generation time (see this class's own doc
     * comment), not fetched live when the report is viewed later.
     *
     * @return array<int, array{date: string, clicks: int}>
     */
    private function dailySeries(SeoProject $project, Carbon $start, Carbon $end): array
    {
        return SeoDailyMetric::where('seo_project_id', $project->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('date')
            ->get(['date', 'clicks'])
            ->map(fn (SeoDailyMetric $row) => [
                'date' => Carbon::parse($row->date)->format('m/d'),
                'clicks' => $row->clicks,
            ])
            ->all();
    }
}
