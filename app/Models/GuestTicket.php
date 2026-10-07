<?php

namespace App\Models;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuestTicket extends Model
{
    protected $fillable = [
        'guest_id', 'subject', 'description', 'status', 'priority',
        'sla_due_at', 'resolved_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'priority' => TicketPriority::class,
            'sla_due_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $ticket) {
            $ticket->sla_due_at ??= now()->addHours($ticket->priority->slaHours());
        });
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(GuestTicketReply::class)->oldest();
    }

    public function isOverdue(): bool
    {
        return $this->sla_due_at
            && $this->sla_due_at->isPast()
            && ! in_array($this->status, [TicketStatus::Resolved, TicketStatus::Closed]);
    }
}
