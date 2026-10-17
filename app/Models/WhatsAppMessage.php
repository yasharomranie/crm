<?php

namespace App\Models;

use App\Domain\Crm\GuestMessenger;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One message in a WhatsAppConversation's thread. 'direction' is a plain
 * string ('inbound'/'outbound') rather than an enum - unlike AiMessage's
 * richer role set (user/assistant/tool/system), a WhatsApp message is
 * never anything but "from the contact" or "from the assistant", since
 * no tools are ever offered on this channel (see
 * WhatsAppInboundMessageHandler's docblock on why).
 */
class WhatsAppMessage extends Model
{
    use HasFactory;

    protected $fillable = ['whats_app_conversation_id', 'direction', 'content'];

    protected static function booted(): void
    {
        // Mirrors the message into the matching guest's communication log.
        // rescue() so a hiccup there can never break the WhatsApp flow itself.
        static::created(fn (self $message) => rescue(fn () => app(GuestMessenger::class)->recordWhatsAppMessage($message)));
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'whats_app_conversation_id');
    }
}
