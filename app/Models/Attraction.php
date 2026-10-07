<?php

namespace App\Models;

use App\Enums\AttractionCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A real, physical place in Hamedan the AI trip planner (see
 * App\Domain\TripPlanner\TripPlannerService) can schedule a guest into -
 * the data this app was missing entirely before (Product is a sellable
 * ticketing item, not a geo/place record). Admin-editable "boost_weight"
 * (prioritize/suppress in suggestions), "closed_from"/"closed_until" (an
 * ad-hoc temporary closure - festival, maintenance, bad weather) and
 * "admin_note" (free text injected straight into the AI prompt, e.g. "این
 * هفته تخفیف ویژه") are exactly the levers that let staff steer what the
 * assistant proposes without a deploy.
 */
class Attraction extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'category', 'description', 'image', 'lat', 'lng', 'address',
        'avg_visit_minutes', 'opens_at', 'closes_at', 'closed_from', 'closed_until',
        'boost_weight', 'admin_note', 'is_active', 'sort_order', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'category' => AttractionCategory::class,
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'closed_from' => 'date',
            'closed_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $attraction) {
            $attraction->slug ??= Str::slug($attraction->name);
        });
    }

    public function tripPlanItems(): HasMany
    {
        return $this->hasMany(TripPlanItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function imageUrl(): ?string
    {
        return $this->image ? Storage::disk('public')->url($this->image) : null;
    }

    /**
     * A temporary closure (festival, maintenance, road closed by weather)
     * set by an admin for a specific date range - independent of
     * is_active, which is for permanently retiring a place.
     */
    public function isClosedOn(\DateTimeInterface $date): bool
    {
        if (! $this->closed_from || ! $this->closed_until) {
            return false;
        }

        return $date >= $this->closed_from && $date <= $this->closed_until;
    }

    /**
     * Whether this attraction's temporary closure window overlaps any part
     * of the given date range - used by TripPlannerService to exclude it
     * from an entire trip, not just a single day.
     */
    public function isClosedBetween(\DateTimeInterface $from, \DateTimeInterface $to): bool
    {
        if (! $this->closed_from || ! $this->closed_until) {
            return false;
        }

        return $this->closed_from <= $to && $this->closed_until >= $from;
    }

    /**
     * Google Maps turn-by-turn directions link - no API key needed, opens
     * the native Maps app on mobile or google.com/maps on desktop.
     */
    public function directionsUrl(): string
    {
        return "https://www.google.com/maps/dir/?api=1&destination={$this->lat},{$this->lng}";
    }
}
