<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
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
        'overview', 'status', 'error_message', 'fingerprint', 'generation_method', 'generation_ms', 'attractions_sent',
        'share_token', 'rating', 'feedback', 'reminder_sent_at',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_BUILDING = 'building';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    /** A plan still waiting or building after this long is treated as lost (the worker died). */
    public const STALL_MINUTES = 5;

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'interests' => 'array',
            'has_car' => 'boolean',
            'reminder_sent_at' => 'datetime',
        ];
    }

    public function scopeReady(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_READY);
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }

    public function isInProgress(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_BUILDING], true);
    }

    /**
     * Still being built, and recently enough that it can plausibly finish.
     */
    public function isBeingBuilt(): bool
    {
        return $this->isInProgress() && $this->updated_at->gt(now()->subMinutes(self::STALL_MINUTES));
    }

    public function hasFailed(): bool
    {
        return $this->status === self::STATUS_FAILED || ($this->isInProgress() && ! $this->isBeingBuilt());
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

    /**
     * The calendar date of one day of the trip (plans made without a start
     * date count from the day they were made).
     */
    public function dateForDay(int $dayNumber): Carbon
    {
        return ($this->starts_on ?? $this->created_at)->copy()->startOfDay()->addDays($dayNumber - 1);
    }
}
