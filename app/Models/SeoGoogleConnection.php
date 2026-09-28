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
        'status', 'last_synced_at', 'last_sync_error',
    ];

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
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoProject::class, 'seo_project_id');
    }

    public function isTokenExpired(): bool
    {
        return $this->token_expires_at !== null && $this->token_expires_at->isPast();
    }
}
