<?php

namespace App\Models;

use Database\Factories\SeoTrackedKeywordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A keyword a project wants its actual Google rank tracked for, separate
 * from SeoKeywordProfile (which only ever reflects keywords Search
 * Console already reported impressions for) - see
 * App\Domain\Seo\RankCheckService for how the daily position gets
 * recorded (App\Models\SeoKeywordRankCheck).
 */
class SeoTrackedKeyword extends Model
{
    /** @use HasFactory<SeoTrackedKeywordFactory> */
    use HasFactory;

    protected $fillable = ['seo_project_id', 'keyword', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoProject::class, 'seo_project_id');
    }

    public function rankChecks(): HasMany
    {
        return $this->hasMany(SeoKeywordRankCheck::class);
    }

    public function latestRankCheck(): HasOne
    {
        return $this->hasOne(SeoKeywordRankCheck::class)->latestOfMany('date');
    }
}
