<?php

namespace App\Models;

use Database\Factories\SeoCompetitorSitemapEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One URL seen inside a competitor's sitemap.xml - the competitor-facing
 * mirror of App\Models\SeoSitemapEntry, kept as its own table rather than
 * a shared one because the shape genuinely differs: a competitor entry
 * also carries topic_cluster_id (which of MY topic clusters this page
 * was matched to, by App\Domain\Seo\CompetitorTopicMatcher), which is
 * meaningless for my own sitemap entries. The only writer of this table
 * is App\Domain\Seo\CompetitorSitemapCrawler.
 */
class SeoCompetitorSitemapEntry extends Model
{
    /** @use HasFactory<SeoCompetitorSitemapEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'seo_project_competitor_id', 'url', 'url_hash', 'lastmod', 'title',
        'topic_cluster_id', 'classified_at', 'first_seen_at', 'last_seen_at', 'last_changed_at',
    ];

    protected function casts(): array
    {
        return [
            'lastmod' => 'datetime',
            'classified_at' => 'datetime',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'last_changed_at' => 'datetime',
        ];
    }

    public function competitor(): BelongsTo
    {
        return $this->belongsTo(SeoProjectCompetitor::class, 'seo_project_competitor_id');
    }

    public function topicCluster(): BelongsTo
    {
        return $this->belongsTo(SeoTopicCluster::class);
    }

    public function wasEdited(): bool
    {
        return $this->last_changed_at->notEqualTo($this->first_seen_at);
    }
}
