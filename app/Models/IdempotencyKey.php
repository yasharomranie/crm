<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdempotencyKey extends Model
{
    use HasFactory;

    protected $fillable = ['external_api_client_id', 'key', 'request_hash', 'response_status', 'response_body'];

    protected function casts(): array
    {
        return ['response_body' => 'array'];
    }

    public function externalApiClient(): BelongsTo
    {
        return $this->belongsTo(ExternalApiClient::class);
    }
}
