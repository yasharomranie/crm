<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One WhatsApp contact's thread with a given assistant profile - separate
 * from AiConversation (which always belongs to a logged-in panel user)
 * since a WhatsApp contact is an external guest with no CRM user account,
 * identified only by their WhatsApp id (wa_id, Meta's phone-number-based
 * identifier).
 */
class WhatsAppConversation extends Model
{
    use HasFactory;

    protected $fillable = ['ai_assistant_profile_id', 'wa_id', 'contact_name'];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(AiAssistantProfile::class, 'ai_assistant_profile_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class)->oldest();
    }
}
