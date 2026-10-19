<?php

namespace App\Models;

use App\Enums\SeoConnectionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoGoogleConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'seo_project_id', 'google_account_email', 'access_token', 'refresh_token',
        'token_expires_at', 'granted_scopes', 'gsc_site_url', 'ga4_property_id',
        'status', 'last_synced_at', 'last_sync_error', 'ga_summary', 'ga_synced_at', 'ga_last_error',
    ];

    public const ANALYTICS_SCOPE = 'https://www.googleapis.com/auth/analytics.readonly';

    protected function casts(): array
    {
        return [
            // Encrypted at rest via APP_KEY (Laravel's built-in encrypted
            // cast) - same pattern as AiProvider::$api_key. These are real
            // Google OAuth credentials, never logged or displayed again
            // once saved (see SeoGoogleConnectionResource/infolist, which
            // must never render them back).
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'granted_scopes' => 'array',
            'status' => SeoConnectionStatus::class,
            'last_synced_at' => 'datetime',
            'ga_summary' => 'array',
            'ga_synced_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoProject::class, 'seo_project_id');
    }

    /**
     * Whether the Google account agreed to share Analytics data (Google lets
     * a user untick one permission on the consent screen).
     */
    public function canReadAnalytics(): bool
    {
        return in_array(self::ANALYTICS_SCOPE, $this->granted_scopes ?? [], true);
    }

    public function isTokenExpired(): bool
    {
        return $this->token_expires_at !== null && $this->token_expires_at->isPast();
    }
}
