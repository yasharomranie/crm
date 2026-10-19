<?php

namespace App\Domain\Seo\WpConnector;

use App\Models\SeoWpNotFoundUrl;
use App\Models\SeoWpSearchTerm;
use App\Models\SeoWpSite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Stores the periodic report the plugin sends with its heartbeat: what needs
 * updating on the site, the addresses that answered 404, and (only when the
 * owner allowed it) what visitors searched for. Each part is a snapshot that
 * replaces the previous one, and a part the owner has not allowed is dropped
 * here even if a plugin sent it.
 */
class WpSiteReportIngestor
{
    private const PLUGIN_LIMIT = 30;

    private const NOT_FOUND_LIMIT = 60;

    private const SEARCH_LIMIT = 120;

    /**
     * @param  array<string, mixed>  $report
     */
    public function ingest(SeoWpSite $site, array $report): void
    {
        $now = now();

        DB::transaction(function () use ($site, $report, $now) {
            if ($site->allows('health')) {
                $this->storeHealth($site, $report, $now);
            }

            if ($site->allows('health') && array_key_exists('not_found', $report)) {
                $this->replaceNotFound($site, (array) $report['not_found'], $now);
            }

            if ($site->allows('search_terms') && array_key_exists('search_terms', $report)) {
                $this->replaceSearchTerms($site, (array) $report['search_terms'], $now);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function storeHealth(SeoWpSite $site, array $report, \DateTimeInterface $now): void
    {
        if (! array_key_exists('outdated_plugins', $report) && ! array_key_exists('wp_core_update', $report)) {
            return;
        }

        $site->update([
            'health' => [
                'wp_core_update' => $report['wp_core_update'] ?? null,
                'outdated_plugins' => collect($report['outdated_plugins'] ?? [])
                    ->map(fn ($plugin) => ['name' => Str::limit((string) ($plugin['name'] ?? ''), 120, ''), 'new_version' => $plugin['new_version'] ?? null])
                    ->filter(fn (array $plugin) => $plugin['name'] !== '')
                    ->take(self::PLUGIN_LIMIT)
                    ->values()
                    ->all(),
                'outdated_themes' => (int) ($report['outdated_themes'] ?? 0),
                'ssl' => isset($report['ssl']) ? (bool) $report['ssl'] : null,
                'cron_disabled' => (bool) ($report['cron_disabled'] ?? false),
            ],
            'health_reported_at' => $now,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function replaceNotFound(SeoWpSite $site, array $rows, \DateTimeInterface $now): void
    {
        $site->notFoundUrls()->delete();

        $records = collect($rows)
            ->filter(fn ($row) => is_array($row) && filled($row['path'] ?? null))
            ->take(self::NOT_FOUND_LIMIT)
            ->map(fn (array $row) => [
                'seo_wp_site_id' => $site->id,
                'path' => Str::limit((string) $row['path'], 255, ''),
                'path_hash' => sha1((string) $row['path']),
                'hits' => max(0, (int) ($row['hits'] ?? 0)),
                'reported_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->unique('path_hash')
            ->values()
            ->all();

        if ($records !== []) {
            SeoWpNotFoundUrl::insert($records);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function replaceSearchTerms(SeoWpSite $site, array $rows, \DateTimeInterface $now): void
    {
        $site->searchTerms()->delete();

        $records = collect($rows)
            ->filter(fn ($row) => is_array($row) && filled($row['term'] ?? null))
            ->take(self::SEARCH_LIMIT)
            ->map(fn (array $row) => [
                'seo_wp_site_id' => $site->id,
                'term' => Str::limit((string) $row['term'], 120, ''),
                'term_hash' => sha1(WpContentInventory::normalize((string) $row['term'])),
                'count' => max(0, (int) ($row['count'] ?? 0)),
                'misses' => max(0, (int) ($row['misses'] ?? 0)),
                'reported_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->unique('term_hash')
            ->values()
            ->all();

        if ($records !== []) {
            SeoWpSearchTerm::insert($records);
        }
    }
}
