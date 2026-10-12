<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeoProjectCompetitor extends Model
{
    use HasFactory;

    protected $fillable = ['seo_project_id', 'domain', 'sitemap_url', 'notes'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoProject::class, 'seo_project_id');
    }

    public function sitemapEntries(): HasMany
    {
        return $this->hasMany(SeoCompetitorSitemapEntry::class);
    }
}
