<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A web page an assistant profile's owner wants watched for changes - the
 * passive counterpart to the fetch_url tool (HasCustomAssistantChatEngine):
 * instead of fetching on request inside a live chat, WatchedSourceChecker
 * re-fetches this URL on a daily schedule and, when its content differs
 * from last_content_hash, drops a new knowledge entry for the owner to
 * review, so the profile stays current even between conversations.
 */
class AiAssistantWatchedSource extends Model
{
    use HasFactory;

    protected $fillable = ['ai_assistant_profile_id', 'url', 'label'];

    protected function casts(): array
    {
        return [
            'last_checked_at' => 'datetime',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(AiAssistantProfile::class, 'ai_assistant_profile_id');
    }

    public function displayLabel(): string
    {
        return $this->label !== null && trim($this->label) !== '' ? $this->label : $this->url;
    }
}
