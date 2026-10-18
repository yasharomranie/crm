<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * One AI-generated Hamedan itinerary for a signed-in guest (see
 * App\Domain\TripPlanner\TripPlannerService::generate()). Flattened into
 * TripPlanItem rows (one per scheduled stop) rather than a single JSON
 * blob, so each stop can join straight to its Attraction (for the image,
 * category icon and directions link) without re-parsing anything.
 */
class TripPlan extends Model
{
    protected $fillable = [
        'guest_id', 'days', 'starts_on', 'interests', 'pace', 'group_type', 'has_car', 'budget_level',
        'overview', 'fingerprint', 'generation_method', 'generation_ms', 'attractions_sent',
        'share_token', 'rating', 'feedback', 'reminder_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'interests' => 'array',
            'has_car' => 'boolean',
            'reminder_sent_at' => 'datetime',
        ];
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TripPlanItem::class)->orderBy('day_number')->orderBy('sort_order');
    }

    /**
     * @return Collection<int, Collection<int, TripPlanItem>>
     */
    public function itemsByDay(): Collection
    {
        return $this->items()->with('attraction')->get()->groupBy('day_number');
    }
}
