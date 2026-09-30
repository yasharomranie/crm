<?php

namespace App\Models;

use Database\Factories\SeoSitemapEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One URL seen inside a project's sitemap.xml, with enough history
 * (first_seen_at / last_seen_at / last_changed_at) to tell "recently
 * added" apart from "recently edited" across syncs - see
 * App\Domain\Seo\SitemapMonitorService, which is the only writer of
 * this table.
 */
class SeoSitemapEntry extends Model
{
    /** @use HasFactory<SeoSitemapEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'seo_project_id', 'url', 'url_hash', 'lastmod', 'title',
        'first_seen_at', 'last_seen_at', 'last_changed_at',
    ];

    protected function casts(): array
    {
        return [
            'lastmod' => 'datetime',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'last_changed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoProject::class, 'seo_project_id');
    }

    public function wasEdited(): bool
    {
        return $this->last_changed_at->notEqualTo($this->first_seen_at);
    }
}
