<?php

namespace App\Models;

use Database\Factories\SeoSiteCheckFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The latest "is the site up, and when does its certificate expire" reading
 * for one SEO project, plus what has already been alerted so a site that
 * stays down or a certificate that stays close to expiry pages someone once,
 * not every fifteen minutes.
 */
class SeoSiteCheck extends Model
{
    /** @use HasFactory<SeoSiteCheckFactory> */
    use HasFactory;

    protected $fillable = [
        'seo_project_id', 'is_up', 'http_status', 'response_ms', 'ssl_expires_at', 'error',
        'consecutive_failures', 'alerted_down_at', 'alerted_ssl_days', 'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'is_up' => 'boolean',
            'ssl_expires_at' => 'datetime',
            'alerted_down_at' => 'datetime',
            'checked_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoProject::class, 'seo_project_id');
    }

    /**
     * @return int|null whole days until the certificate expires (negative once it has), null when unknown
     */
    public function sslDaysLeft(): ?int
    {
        return $this->ssl_expires_at ? (int) floor(now()->diffInDays($this->ssl_expires_at, false)) : null;
    }
}
