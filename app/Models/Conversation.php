<?php

namespace App\Models;

use App\Enums\ConversationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = ['type', 'name', 'created_by'];

    protected function casts(): array
    {
        return [
            'type' => ConversationType::class,
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /**
     * Conversations the given user is a participant of, most recently
     * active first (Message::$touches keeps conversations.updated_at
     * current).
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query
            ->whereHas('participants', fn (Builder $query) => $query->where('user_id', $user->id))
            ->latest('updated_at');
    }

    /**
     * Finds the existing 1:1 conversation between these two users, or
     * starts a new one. Prevents duplicate direct threads between the same
     * pair.
     */
    public static function findOrCreateDirect(User $a, User $b): self
    {
        $aConversationIds = ConversationParticipant::query()
            ->where('user_id', $a->id)
            ->pluck('conversation_id');

        $existing = static::query()
            ->where('type', ConversationType::Direct)
            ->whereIn('id', $aConversationIds)
            ->whereHas('participants', fn (Builder $query) => $query->where('user_id', $b->id))
            ->withCount('participants')
            ->get()
            ->firstWhere('participants_count', 2);

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($a, $b) {
            $conversation = static::create([
                'type' => ConversationType::Direct,
                'created_by' => $a->id,
            ]);

            $conversation->participants()->createMany([
                ['user_id' => $a->id],
                ['user_id' => $b->id],
            ]);

            return $conversation;
        });
    }

    /**
     * @param  array<int>  $participantUserIds
     */
    public static function createGroup(string $name, User $creator, array $participantUserIds): self
    {
        return DB::transaction(function () use ($name, $creator, $participantUserIds) {
            $conversation = static::create([
                'type' => ConversationType::Group,
                'name' => $name,
                'created_by' => $creator->id,
            ]);

            $userIds = collect($participantUserIds)->push($creator->id)->unique();

            $conversation->participants()->createMany(
                $userIds->map(fn (int $userId) => ['user_id' => $userId])->all()
            );

            return $conversation;
        });
    }

    /**
     * What to show in the conversation list / thread header: the other
     * person's name for a direct conversation, or the group's name.
     */
    public function displayNameFor(User $viewer): string
    {
        if ($this->type === ConversationType::Group) {
            return $this->name ?: 'گروه بدون‌نام';
        }

        $other = $this->participants->firstWhere('user_id', '!=', $viewer->id);

        return $other?->user?->name ?? 'کاربر حذف‌شده';
    }

    public function otherParticipantFor(User $viewer): ?User
    {
        return $this->participants->firstWhere('user_id', '!=', $viewer->id)?->user;
    }

    public function unreadCountFor(User $user): int
    {
        $participant = $this->participants->firstWhere('user_id', $user->id);

        if (! $participant) {
            return 0;
        }

        return $this->messages()
            ->where('sender_id', '!=', $user->id)
            ->when(
                $participant->last_read_message_id,
                fn (Builder $query, int $lastId) => $query->where('id', '>', $lastId),
            )
            ->count();
    }

    public function markReadFor(User $user): void
    {
        $latestId = $this->messages()->max('id');

        if (! $latestId) {
            return;
        }

        $this->participants()
            ->where('user_id', $user->id)
            ->update(['last_read_message_id' => $latestId]);
    }

    /**
     * The lowest last_read_message_id among every participant OTHER than
     * the given user - used to render read-receipt ticks on messages the
     * user sent (all-read only once every other participant has caught
     * up, matching group-chat conventions).
     */
    public function lastReadMessageIdExcluding(User $user): ?int
    {
        $others = $this->participants->where('user_id', '!=', $user->id);

        if ($others->isEmpty() || $others->contains(fn (ConversationParticipant $p) => is_null($p->last_read_message_id))) {
            return null;
        }

        return $others->min('last_read_message_id');
    }
}
