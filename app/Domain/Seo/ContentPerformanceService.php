<?php

namespace App\Domain\Seo;

use App\Models\SeoContentItem;
use App\Models\SeoPageMetric;

/**
 * The "measurement" step of مرکز سئو's data -> analysis -> opportunity ->
 * action -> measurement -> learning loop: once a SeoContentItem is
 * published, this matches its published_url against the already-synced
 * warehouse (SeoPageMetric) to show whether it actually moved the
 * needle, without any new external API call - Search Console data for
 * that URL keeps flowing in through the normal sync job.
 */
class ContentPerformanceService
{
    /**
     * @return array{clicks: int, impressions: int, avg_position: ?float, days_since_publish: ?int}
     */
    public function summarize(SeoContentItem $item): array
    {
        if (blank($item->published_url) || ! $item->published_at) {
            return ['clicks' => 0, 'impressions' => 0, 'avg_position' => null, 'days_since_publish' => null];
        }

        $row = SeoPageMetric::query()
            ->where('seo_project_id', $item->seo_project_id)
            ->where('page_url', $item->published_url)
            ->whereDate('date', '>=', $item->published_at->toDateString())
            ->selectRaw('coalesce(sum(clicks), 0) as clicks, coalesce(sum(impressions), 0) as impressions, avg(position) as avg_position')
            ->first();

        return [
            'clicks' => (int) $row->clicks,
            'impressions' => (int) $row->impressions,
            'avg_position' => $row->avg_position !== null ? round((float) $row->avg_position, 1) : null,
            'days_since_publish' => (int) $item->published_at->diffInDays(now()),
        ];
    }
}
