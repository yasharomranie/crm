<?php

namespace App\Models;

use Database\Factories\SmsSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A singleton settings row (always id=1) rather than a real CRUD resource -
 * every part of the system that needs to send an SMS (not just the events
 * feature) should read the sms.ir credentials from here via current(),
 * instead of each caller reaching into .env/config on its own.
 */
class SmsSetting extends Model
{
    /** @use HasFactory<SmsSettingFactory> */
    use HasFactory;

    protected $fillable = ['is_enabled', 'api_key', 'line_number'];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'api_key' => 'encrypted',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }

    public function isUsable(): bool
    {
        return $this->is_enabled && filled($this->api_key) && filled($this->line_number);
    }
}
