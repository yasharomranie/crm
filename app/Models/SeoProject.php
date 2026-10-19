<?php

namespace App\Models;

use App\Enums\SeoProjectGoal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class SeoProject extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'website_url', 'sitemap_url', 'goal', 'audience', 'real_services', 'excluded_query_terms',
        'market', 'brand', 'notes', 'is_active', 'google_sitemap_status', 'google_sitemap_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'goal' => SeoProjectGoal::class,
            'is_active' => 'boolean',
            'google_sitemap_status' => 'array',
            'google_sitemap_checked_at' => 'datetime',
        ];
    }

    /**
     * Brand/noise terms (one per line) the admin wants excluded from
     * keyword-level Search Console sync (see SyncSearchConsoleDataJob) -
     * e.g. the brand name itself, so "گنجنامه بلیط" doesn't clutter the
     * keyword-opportunity list with traffic that was never really about
     * discovering the business.
     *
     * @return array<int, string>
     */
    public function excludedQueryTermsList(): array
    {
        return collect(preg_split('/[\r\n,]+/', (string) $this->excluded_query_terms))
            ->map(fn (string $term) => trim($term))
            ->filter()
            ->values()
            ->all();
    }

    public function competitors(): HasMany
    {
        return $this->hasMany(SeoProjectCompetitor::class);
    }

    public function wpSite(): HasOne
    {
        return $this->hasOne(SeoWpSite::class);
    }

    public function siteCheck(): HasOne
    {
        return $this->hasOne(SeoSiteCheck::class);
    }

    public function googleConnection(): HasOne
    {
        return $this->hasOne(SeoGoogleConnection::class);
    }

    public function dailyMetrics(): HasMany
    {
        return $this->hasMany(SeoDailyMetric::class);
    }

    public function queryMetrics(): HasMany
    {
        return $this->hasMany(SeoQueryMetric::class);
    }

    public function pageMetrics(): HasMany
    {
        return $this->hasMany(SeoPageMetric::class);
    }

    public function queryPageMetrics(): HasMany
    {
        return $this->hasMany(SeoQueryPageMetric::class);
    }

    public function countryMetrics(): HasMany
    {
        return $this->hasMany(SeoCountryMetric::class);
    }

    public function deviceMetrics(): HasMany
    {
        return $this->hasMany(SeoDeviceMetric::class);
    }

    public function searchAppearanceMetrics(): HasMany
    {
        return $this->hasMany(SeoSearchAppearanceMetric::class);
    }

    public function searchTypeMetrics(): HasMany
    {
        return $this->hasMany(SeoSearchTypeMetric::class);
    }

    public function topicClusters(): HasMany
    {
        return $this->hasMany(SeoTopicCluster::class);
    }

    public function keywordProfiles(): HasMany
    {
        return $this->hasMany(SeoKeywordProfile::class);
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(SeoOpportunity::class);
    }

    public function keywordSuggestions(): HasMany
    {
        return $this->hasMany(SeoKeywordSuggestion::class);
    }

    public function contentItems(): HasMany
    {
        return $this->hasMany(SeoContentItem::class);
    }

    public function trackedKeywords(): HasMany
    {
        return $this->hasMany(SeoTrackedKeyword::class);
    }

    public function technicalAudits(): HasMany
    {
        return $this->hasMany(SeoTechnicalAudit::class);
    }

    public function sitemapEntries(): HasMany
    {
        return $this->hasMany(SeoSitemapEntry::class);
    }

    /**
     * @return Collection<int, SeoSitemapEntry>
     */
    public function recentlyAddedSitemapPages(int $limit = 5): Collection
    {
        return $this->sitemapEntries()->orderByDesc('first_seen_at')->limit($limit)->get();
    }

    /**
     * Only entries whose lastmod actually changed after they were first
     * discovered - see App\Domain\Seo\SitemapMonitorService.
     *
     * @return Collection<int, SeoSitemapEntry>
     */
    public function recentlyEditedSitemapPages(int $limit = 5): Collection
    {
        return $this->sitemapEntries()
            ->whereColumn('last_changed_at', '>', 'first_seen_at')
            ->orderByDesc('last_changed_at')
            ->limit($limit)
            ->get();
    }

    public function sitemapContentVolumeThisMonth(): int
    {
        return $this->sitemapEntries()
            ->whereBetween('first_seen_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();
    }

    public function periodicReports(): HasMany
    {
        return $this->hasMany(SeoPeriodicReport::class);
    }
}
