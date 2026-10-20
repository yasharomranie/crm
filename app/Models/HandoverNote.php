<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line in the front desk's shift handover book: something the next shift
 * must know or do, optionally tied to a booking or room, open until someone
 * marks it done.
 */
class HandoverNote extends Model
{
    use HasFactory;

    protected $fillable = ['body', 'booking_id', 'room_id', 'created_by', 'resolved_at', 'resolved_by'];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<HandoverNote>  $query
     * @return Builder<HandoverNote>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    public function isOpen(): bool
    {
        return $this->resolved_at === null;
    }

    public function resolve(?int $userId = null): void
    {
        if ($this->isOpen()) {
            $this->update(['resolved_at' => now(), 'resolved_by' => $userId]);
        }
    }
}
