<?php

namespace App\Domain\Seo\WpConnector;

use App\Domain\Seo\SiteHealth\SiteHealthMonitor;
use App\Models\SeoWpPost;
use App\Models\SeoWpSite;
use Illuminate\Support\Collection;

/**
 * One line per connected WordPress site, worst first, so with a hundred
 * sites the ones that need a person stand out: crashed syncs, silent sites,
 * sites that tell search engines to stay away, and plugins behind the
 * version the CRM serves.
 */
class WpFleetStatus
{
    /** Display order and wording of each state, most urgent first. */
    public const STATES = [
        'down' => ['label' => 'سایت در دسترس نیست', 'color' => 'danger'],
        'error' => ['label' => 'خطا در دریافت', 'color' => 'danger'],
        'silent' => ['label' => 'ساکت (پلاگین خبر نمی‌دهد)', 'color' => 'danger'],
        'ssl' => ['label' => 'SSL رو به پایان', 'color' => 'warning'],
        'discouraged' => ['label' => 'موتور جست‌وجو مسدود است', 'color' => 'warning'],
        'outdated' => ['label' => 'پلاگین قدیمی', 'color' => 'warning'],
        'pending' => ['label' => 'در انتظار اتصال', 'color' => 'gray'],
        'disconnected' => ['label' => 'قطع‌شده', 'color' => 'gray'],
        'ok' => ['label' => 'سالم', 'color' => 'success'],
    ];

    public function __construct(private WpPluginPackage $package) {}

    /**
     * @return array{latest_version: string, counts: array<string, int>, rows: Collection<int, array<string, mixed>>}
     */
    public function overview(?string $state = null): array
    {
        $latest = $this->package->version();

        $sites = SeoWpSite::query()->with('project:id,name', 'project.siteCheck')->withCount('posts')->get();

        $withIssues = SeoWpPost::query()
            ->selectRaw('seo_wp_site_id, count(*) as total')
            ->whereNotNull('issues')
            ->where('issues', '!=', '[]')
            ->groupBy('seo_wp_site_id')
            ->pluck('total', 'seo_wp_site_id');

        $rows = $sites->map(function (SeoWpSite $site) use ($latest, $withIssues) {
            $state = $this->stateOf($site, $latest);
            $check = $site->project?->siteCheck;

            return [
                'site_id' => $site->id,
                'project_id' => $site->seo_project_id,
                'project' => $site->project?->name ?? '—',
                'url' => $site->site_url,
                'state' => $state,
                'state_label' => self::STATES[$state]['label'],
                'state_color' => self::STATES[$state]['color'],
                'plugin_version' => $site->plugin_version,
                'seo_plugin' => $site->seo_plugin,
                'has_woocommerce' => $site->has_woocommerce,
                'posts' => $site->posts_count,
                'posts_with_issues' => (int) ($withIssues[$site->id] ?? 0),
                'last_seen_at' => $site->last_seen_at,
                'last_sync_at' => $site->last_sync_at,
                'last_error' => $site->last_error,
                'response_ms' => $check?->response_ms,
                'ssl_days_left' => $check?->sslDaysLeft(),
            ];
        });

        $counts = array_fill_keys(array_keys(self::STATES), 0);

        foreach ($rows as $row) {
            $counts[$row['state']]++;
        }

        $order = array_flip(array_keys(self::STATES));

        $rows = $rows
            ->when($state, fn (Collection $rows) => $rows->where('state', $state))
            ->sortBy([fn ($a, $b) => $order[$a['state']] <=> $order[$b['state']], fn ($a, $b) => $b['posts_with_issues'] <=> $a['posts_with_issues']])
            ->values();

        return ['latest_version' => $latest, 'counts' => $counts, 'rows' => $rows];
    }

    private function stateOf(SeoWpSite $site, string $latest): string
    {
        $check = $site->project?->siteCheck;
        $sslDays = $check?->sslDaysLeft();

        return match (true) {
            $site->status === SeoWpSite::STATUS_DISCONNECTED => 'disconnected',
            $site->status === SeoWpSite::STATUS_PENDING => 'pending',
            $check && ! $check->is_up && $check->consecutive_failures >= SiteHealthMonitor::FAILURES_BEFORE_DOWN => 'down',
            filled($site->last_error) => 'error',
            $site->isStale() => 'silent',
            $sslDays !== null && $sslDays <= SiteHealthMonitor::SSL_ALERT_DAYS[0] => 'ssl',
            $site->search_engines_discouraged => 'discouraged',
            $site->plugin_version && version_compare($site->plugin_version, $latest, '<') => 'outdated',
            default => 'ok',
        };
    }
}
