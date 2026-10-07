<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One message in a guest ticket's reply thread - from either the guest
 * themselves (via وب‌اپ مشتریان's "تیکت پشتیبانی" page) or a staff member
 * (via the "پاسخ‌ها" relation manager on GuestTicketResource). Exactly one
 * of guest_id/user_id is set, identifying who wrote it.
 */
class GuestTicketReply extends Model
{
    protected $fillable = ['guest_ticket_id', 'guest_id', 'user_id', 'body'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(GuestTicket::class, 'guest_ticket_id');
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isFromGuest(): bool
    {
        return $this->guest_id !== null;
    }

    public function authorName(): string
    {
        return $this->isFromGuest()
            ? ($this->guest?->name ?? 'مشتری')
            : ($this->user?->name ?? 'پشتیبانی');
    }
}
