<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The WordPress site behind one SEO project, connected by the CRM
 * connector plugin. The plugin sends what the site owner allowed it to
 * (scopes); this row holds the shared signing secret, what the plugin
 * reported about itself and when it last spoke.
 */
class SeoWpSite extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONNECTED = 'connected';

    public const STATUS_DISCONNECTED = 'disconnected';

    /** A connected site that has been silent this long needs a look. */
    public const STALE_AFTER_HOURS = 12;

    protected $fillable = [
        'seo_project_id', 'pairing_code_hash', 'pairing_expires_at', 'secret', 'site_url', 'plugin_version',
        'wp_version', 'php_version', 'seo_plugin', 'has_woocommerce', 'search_engines_discouraged', 'scopes', 'status',
        'last_seen_at', 'last_sync_at', 'analyzed_at', 'last_error', 'health', 'health_reported_at',
    ];

    protected $hidden = ['secret', 'pairing_code_hash'];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'scopes' => 'array',
            'health' => 'array',
            'health_reported_at' => 'datetime',
            'has_woocommerce' => 'boolean',
            'search_engines_discouraged' => 'boolean',
            'analyzed_at' => 'datetime',
            'pairing_expires_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'last_sync_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoProject::class, 'seo_project_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(SeoWpPost::class);
    }

    public function notFoundUrls(): HasMany
    {
        return $this->hasMany(SeoWpNotFoundUrl::class);
    }

    public function searchTerms(): HasMany
    {
        return $this->hasMany(SeoWpSearchTerm::class);
    }

    public function isConnected(): bool
    {
        return $this->status === self::STATUS_CONNECTED && filled($this->secret);
    }

    public function isStale(): bool
    {
        return $this->isConnected()
            && (! $this->last_seen_at || $this->last_seen_at->lt(now()->subHours(self::STALE_AFTER_HOURS)));
    }

    public function allows(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? [], true);
    }
}
