<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = ['conversation_id', 'sender_id', 'body', 'file_path', 'file_name', 'mime_type', 'size_kb'];

    /**
     * Whether this message's attachment (if any) is an image - callers use
     * this to decide between an inline <img> preview and a generic
     * file-download row (see messenger.blade.php).
     */
    public function attachmentIsImage(): bool
    {
        return $this->mime_type !== null && str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Whether this message's attachment (if any) is a voice note/audio
     * clip - rendered as an <audio> player instead of a download row.
     */
    public function attachmentIsAudio(): bool
    {
        return $this->mime_type !== null && str_starts_with($this->mime_type, 'audio/');
    }

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
