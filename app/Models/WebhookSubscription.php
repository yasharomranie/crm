<?php

namespace App\Models;

use App\Enums\WebhookEventType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_api_client_id', 'event_type', 'target_url', 'signing_secret', 'is_active', 'last_triggered_at',
        'last_failed_at', 'last_error', 'consecutive_failures',
    ];

    protected $hidden = ['signing_secret'];

    protected function casts(): array
    {
        return [
            'event_type' => WebhookEventType::class,
            'is_active' => 'boolean',
            'last_triggered_at' => 'datetime',
            'last_failed_at' => 'datetime',
            'consecutive_failures' => 'integer',
        ];
    }

    public function externalApiClient(): BelongsTo
    {
        return $this->belongsTo(ExternalApiClient::class);
    }
}
