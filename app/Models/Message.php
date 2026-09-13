<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = ['conversation_id', 'sender_id', 'body'];

    /**
     * Bumps the parent conversation's updated_at whenever a message is
     * created, so the conversation list can simply order by conversations
     * updated_at desc to show the most recently active thread first.
     */
    protected $touches = ['conversation'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Total messages, across every conversation the given user belongs to,
     * sent by someone else and not yet read - a single joined query rather
     * than summing Conversation::unreadCountFor() per conversation. Powers
     * the "پیام‌رسان" nav badge.
     */
    public static function totalUnreadFor(User $user): int
    {
        return static::query()
            ->join('conversation_participants', function ($join) use ($user) {
                $join->on('conversation_participants.conversation_id', '=', 'messages.conversation_id')
                    ->where('conversation_participants.user_id', $user->id);
            })
            ->where('messages.sender_id', '!=', $user->id)
            ->where(function ($query) {
                $query->whereNull('conversation_participants.last_read_message_id')
                    ->orWhereColumn('messages.id', '>', 'conversation_participants.last_read_message_id');
            })
            ->count();
    }
}
