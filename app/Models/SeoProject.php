<?php

namespace App\Models;

use App\Enums\SeoProjectGoal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SeoProject extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'website_url', 'goal', 'audience', 'market', 'brand', 'notes', 'is_active'];

    protected function casts(): array
    {
        return [
            'goal' => SeoProjectGoal::class,
            'is_active' => 'boolean',
        ];
    }

    public function competitors(): HasMany
    {
        return $this->hasMany(SeoProjectCompetitor::class);
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
}
