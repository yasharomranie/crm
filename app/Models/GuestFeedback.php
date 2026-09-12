<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestFeedback extends Model
{
    // Laravel's pluralizer treats "feedback" as uncountable and would
    // otherwise guess "guest_feedback" (no s) for this model's table.
    protected $table = 'guest_feedbacks';

    protected $fillable = ['guest_id', 'booking_id', 'nps_score', 'comment', 'requested_at', 'submitted_at'];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * NPS buckets: 9-10 promoter, 7-8 passive, 0-6 detractor.
     */
    public function npsCategory(): ?string
    {
        return match (true) {
            $this->nps_score === null => null,
            $this->nps_score >= 9 => 'promoter',
            $this->nps_score >= 7 => 'passive',
            default => 'detractor',
        };
    }
}
