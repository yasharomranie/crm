<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A user-created, tools-free custom AI assistant: a saved system prompt
 * plus a "blob mascot" avatar (shape + color), shown as a card in the AI
 * Assistants Hub (app/Filament/Pages/AiAssistantsHub.php). Unlike the
 * built-in management/legal assistants (HasAiAssistantEngine), a profile
 * runs no tools in Phase 1 - allowed_tools is reserved for a future phase.
 */
class AiAssistantProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by', 'name', 'description', 'avatar_shape', 'avatar_color', 'is_shared', 'allowed_tools',
    ];

    protected function casts(): array
    {
        return [
            'is_shared' => 'boolean',
            'allowed_tools' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(AiConversation::class);
    }

    /**
     * Profiles a given user is allowed to see/chat with: their own, plus
     * anyone else's shared (public) ones.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $query) use ($user) {
            $query->where('created_by', $user->id)->orWhere('is_shared', true);
        });
    }

    public function displayName(): string
    {
        return $this->name !== null && trim($this->name) !== '' ? $this->name : 'دستیار بدون‌نام';
    }
}
