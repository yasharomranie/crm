<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One piece of content on a connected WordPress site (post, page, product
 * or custom type), as the connector plugin reported it. Only derived facts
 * are kept - never the body text - so a site with thousands of posts stays
 * small and nothing private leaves the site by accident.
 */
class SeoWpPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'seo_wp_site_id', 'wp_id', 'post_type', 'status', 'url', 'url_hash', 'title', 'slug', 'author_name',
        'published_at', 'modified_at', 'word_count', 'headings', 'internal_links', 'external_links', 'images',
        'terms', 'seo', 'commerce', 'content_hash', 'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'modified_at' => 'datetime',
            'synced_at' => 'datetime',
            'headings' => 'array',
            'internal_links' => 'array',
            'external_links' => 'array',
            'images' => 'array',
            'terms' => 'array',
            'seo' => 'array',
            'commerce' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(SeoWpSite::class, 'seo_wp_site_id');
    }

    public static function hashUrl(string $url): string
    {
        return sha1(rtrim(mb_strtolower(trim($url)), '/'));
    }
}
