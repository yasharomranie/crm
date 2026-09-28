<?php

namespace App\Models;

use Database\Factories\SeoRankTrackerSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A singleton settings row (always id=1), mirroring SmsSetting/
 * SeoGoogleSetting - App\Domain\Seo\GoogleCustomSearchClient reads the
 * Custom Search JSON API credentials from here (edited from "تنظیمات
 * ترکر کلمه کلیدی" in the panel), not .env, so an admin without server
 * access can configure this too.
 */
class SeoRankTrackerSetting extends Model
{
    /** @use HasFactory<SeoRankTrackerSettingFactory> */
    use HasFactory;

    protected $fillable = ['api_key', 'search_engine_id'];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }

    public function isConfigured(): bool
    {
        return filled($this->api_key) && filled($this->search_engine_id);
    }
}
