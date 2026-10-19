<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something visitors typed into a connected WordPress site's own search
 * box, how often, and how many of those searches found nothing - the
 * snapshot the plugin last reported (only when the owner allowed it).
 */
class SeoWpSearchTerm extends Model
{
    protected $fillable = ['seo_wp_site_id', 'term', 'term_hash', 'count', 'misses', 'reported_at'];

    protected function casts(): array
    {
        return ['reported_at' => 'datetime'];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(SeoWpSite::class, 'seo_wp_site_id');
    }
}
