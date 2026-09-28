<?php

namespace App\Models;

use Database\Factories\SeoKeywordRankCheckFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One day's rank check result for a SeoTrackedKeyword - position is null
 * when the tracked domain wasn't found within the depth
 * App\Domain\Seo\RankCheckService searched (never guessed at a worse
 * position it didn't actually confirm).
 */
class SeoKeywordRankCheck extends Model
{
    /** @use HasFactory<SeoKeywordRankCheckFactory> */
    use HasFactory;

    protected $fillable = ['seo_tracked_keyword_id', 'date', 'position', 'found_url', 'checked_at'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'position' => 'integer',
            'checked_at' => 'datetime',
        ];
    }

    public function trackedKeyword(): BelongsTo
    {
        return $this->belongsTo(SeoTrackedKeyword::class, 'seo_tracked_keyword_id');
    }
}
