<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A singleton settings row (always id=1, see SmsSetting for the same
 * pattern) holding the WhatsApp Cloud API credentials and which custom
 * AiAssistantProfile answers incoming messages - the configuration half
 * of the WhatsApp channel infrastructure (WhatsAppWebhookController,
 * WhatsAppInboundMessageHandler, WhatsAppCloudApiClient). Deliberately
 * infrastructure-only until an owner has a real Meta WhatsApp Business
 * API account to enter here: isUsable() stays false, so the webhook
 * controller rejects everything, until all four fields below are filled
 * in and is_enabled is turned on.
 */
class WhatsAppBusinessSetting extends Model
{
    protected $fillable = ['is_enabled', 'phone_number_id', 'access_token', 'verify_token', 'ai_assistant_profile_id'];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'access_token' => 'encrypted',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(AiAssistantProfile::class, 'ai_assistant_profile_id');
    }

    public function isUsable(): bool
    {
        return $this->is_enabled
            && filled($this->phone_number_id)
            && filled($this->access_token)
            && filled($this->verify_token)
            && $this->ai_assistant_profile_id !== null;
    }
}
