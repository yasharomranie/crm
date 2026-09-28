<?php

namespace App\Models;

use Database\Factories\SeoGoogleSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A singleton settings row (always id=1) rather than a real CRUD resource -
 * App\Domain\Seo\GoogleOAuthService reads the Search Console OAuth client
 * credentials from here via current(), so an admin without server access
 * can enter them from "تنظیمات اتصال گوگل" in the panel instead of editing
 * .env. One OAuth app is shared by every SeoProject: each project still
 * gets its own Google account/Search Console property connection (see
 * SeoGoogleConnection), this just supplies the client_id/client_secret
 * that connection flow authenticates with.
 */
class SeoGoogleSetting extends Model
{
    /** @use HasFactory<SeoGoogleSettingFactory> */
    use HasFactory;

    protected $fillable = ['client_id', 'client_secret'];

    protected function casts(): array
    {
        return [
            'client_secret' => 'encrypted',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }

    public function isConfigured(): bool
    {
        return filled($this->client_id) && filled($this->client_secret);
    }
}
