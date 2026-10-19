<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An address on a connected WordPress site that answered "not found",
 * with how many times - the snapshot the plugin last reported.
 */
class SeoWpNotFoundUrl extends Model
{
    protected $fillable = ['seo_wp_site_id', 'path', 'path_hash', 'hits', 'reported_at'];

    protected function casts(): array
    {
        return ['reported_at' => 'datetime'];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(SeoWpSite::class, 'seo_wp_site_id');
    }
}
