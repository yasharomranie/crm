<?php

namespace App\Models;

use App\Enums\MessageChannel;
use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageDirection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in a guest's communication log - an email or SMS staff sent
 * from the CRM, or a WhatsApp message to/from them (copied in by
 * WhatsAppMessage's created hook). The single table the guest's
 * «مکالمات» tab and the «صندوق پیام» page read from.
 */
class CustomerMessage extends Model
{
    use HasFactory;

    protected $fillable = ['guest_id', 'channel', 'direction', 'subject', 'body', 'status', 'error', 'sent_by'];

    protected function casts(): array
    {
        return [
            'channel' => MessageChannel::class,
            'direction' => MessageDirection::class,
            'status' => MessageDeliveryStatus::class,
        ];
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
