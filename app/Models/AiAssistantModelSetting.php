<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per built-in, single-instance assistant (keyed by a fixed
 * 'assistant_key' such as 'ai-assistant' or 'contract-assistant') holding
 * its preferred chat-completion model. Unlike AiAssistantProfile (which
 * already has its own 'model' column), these two assistants have no
 * per-user DB record of their own to hold this preference on, so it's a
 * tiny standalone settings table instead.
 */
class AiAssistantModelSetting extends Model
{
    use HasFactory;

    protected $fillable = ['assistant_key', 'model'];

    /**
     * The chosen model for a given assistant, or null to fall back to the
     * active provider's own default_model.
     */
    public static function modelFor(string $assistantKey): ?string
    {
        return static::query()->where('assistant_key', $assistantKey)->value('model');
    }

    /**
     * Persists (or clears, when $model is null/blank) the preferred model
     * for a given assistant.
     */
    public static function setModelFor(string $assistantKey, ?string $model): void
    {
        static::query()->updateOrCreate(
            ['assistant_key' => $assistantKey],
            ['model' => filled($model) ? $model : null],
        );
    }
}
