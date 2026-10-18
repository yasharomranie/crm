<?php

namespace App\Models;

use App\Enums\AttractionCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
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
        'closed_weekdays', 'weekday_hours', 'entry_fee', 'phone', 'kid_friendly', 'senior_friendly', 'has_parking',
        'best_time_note', 'product_id', 'source', 'verified_at',
    ];

    /** Carbon::dayOfWeek values (0 = Sunday ... 6 = Saturday) with Persian names, Saturday first. */
    public const WEEKDAY_LABELS = [6 => 'شنبه', 0 => 'یکشنبه', 1 => 'دوشنبه', 2 => 'سه‌شنبه', 3 => 'چهارشنبه', 4 => 'پنجشنبه', 5 => 'جمعه'];

    protected function casts(): array
    {
        return [
            'category' => AttractionCategory::class,
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'closed_from' => 'date',
            'closed_until' => 'date',
            'closed_weekdays' => 'array',
            'weekday_hours' => 'array',
            'entry_fee' => 'integer',
            'kid_friendly' => 'boolean',
            'senior_friendly' => 'boolean',
            'has_parking' => 'boolean',
            'verified_at' => 'datetime',
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
     * Whether the place is shut on this particular date: either inside the
     * ad-hoc closure range, or on a weekday it is always closed.
     */
    public function isClosedOnDate(\DateTimeInterface $date): bool
    {
        $carbon = Carbon::instance($date);

        if (in_array($carbon->dayOfWeek, array_map('intval', $this->closed_weekdays ?? []), true)) {
            return true;
        }

        return $this->closed_from && $this->closed_until
            && $carbon->toDateString() >= $this->closed_from->toDateString()
            && $carbon->toDateString() <= $this->closed_until->toDateString();
    }

    /**
     * The opening hours that apply on a date: the weekday's own hours when
     * the admin set some for it (Friday opens late, Thursday closes early),
     * otherwise the place's usual opens_at / closes_at.
     *
     * @return array{opens_at: ?string, closes_at: ?string}
     */
    public function hoursOn(\DateTimeInterface $date): array
    {
        $weekday = Carbon::instance($date)->dayOfWeek;

        foreach ($this->weekday_hours ?? [] as $entry) {
            if (is_array($entry) && (int) ($entry['weekday'] ?? -1) === $weekday) {
                return ['opens_at' => $entry['opens_at'] ?? null, 'closes_at' => $entry['closes_at'] ?? null];
            }
        }

        return ['opens_at' => $this->opens_at, 'closes_at' => $this->closes_at];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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
