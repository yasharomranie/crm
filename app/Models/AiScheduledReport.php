<?php

namespace App\Models;

use Database\Factories\AiScheduledReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Every morning, tell me who has not checked in" - a question a manager
 * wants the management assistant to answer on its own once a day. It runs
 * as that manager (their permissions apply), with read-only tools, and the
 * answer is saved as a conversation they can open from the notification.
 */
class AiScheduledReport extends Model
{
    /** @use HasFactory<AiScheduledReportFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'prompt', 'run_at_time', 'is_active', 'last_run_at', 'last_conversation_id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lastConversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'last_conversation_id');
    }
}
