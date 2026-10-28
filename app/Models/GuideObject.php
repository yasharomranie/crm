<?php

namespace App\Models;

use App\Enums\GuideObjectCategory;
use App\Enums\GuideObjectStatus;
use App\Enums\GuideReferenceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Something visitors can point their phone at (an inscription, a statue, a
 * stuffed animal...). It becomes recognisable ("ready") once enough of its
 * reference photos have been processed; only ready, active objects are ever
 * handed to the visitors' phones.
 */
class GuideObject extends Model
{
    use HasFactory;

    protected $fillable = [
        'guide_site_id', 'guide_zone_id', 'title_fa', 'title_en', 'slug', 'category', 'description',
        'location_note', 'cover_image', 'status', 'is_active', 'ready_at', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'category' => GuideObjectCategory::class,
            'status' => GuideObjectStatus::class,
            'is_active' => 'boolean',
            'ready_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $object): void {
            if (blank($object->slug)) {
                $object->slug = (Str::slug((string) $object->title_en) ?: 'object').'-'.Str::lower(Str::random(6));
            }
        });

        static::saving(function (self $object): void {
            if (! $object->is_active) {
                $object->status = GuideObjectStatus::Disabled;
            } elseif ($object->isDirty('is_active') && $object->exists) {
                $object->status = $object->computedStatus();
                $object->ready_at = $object->status === GuideObjectStatus::Ready ? ($object->ready_at ?? now()) : null;
            }
        });
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(GuideSite::class, 'guide_site_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(GuideZone::class, 'guide_zone_id');
    }

    public function referenceImages(): HasMany
    {
        return $this->hasMany(GuideReferenceImage::class);
    }

    public function videos(): HasMany
    {
        return $this->hasMany(GuideVideo::class)->orderBy('sort_order')->orderBy('id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(GuideEvent::class);
    }

    /** What visitors may be shown: switched on, ready, and in an active site. */
    public function scopeRecognisable(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('status', GuideObjectStatus::Ready->value)
            ->whereHas('site', fn (Builder $site) => $site->where('is_active', true));
    }

    public function coverUrl(): ?string
    {
        return filled($this->cover_image) ? Storage::disk('public')->url($this->cover_image) : null;
    }

    /**
     * The status the reference photos justify: no photos is a draft, photos
     * still waiting make it processing, enough processed photos make it ready,
     * and a finished batch that stayed short is failed.
     */
    public function computedStatus(): GuideObjectStatus
    {
        $statuses = $this->referenceImages()->pluck('status');

        if ($statuses->isEmpty()) {
            return GuideObjectStatus::Draft;
        }

        if ($statuses->contains(fn ($status) => $this->asReferenceStatus($status) === GuideReferenceStatus::Pending)) {
            return GuideObjectStatus::Processing;
        }

        $processed = $statuses->filter(fn ($status) => $this->asReferenceStatus($status) === GuideReferenceStatus::Processed)->count();

        return $processed >= GuideSetting::current()->min_reference_images ? GuideObjectStatus::Ready : GuideObjectStatus::Failed;
    }

    private function asReferenceStatus(mixed $status): ?GuideReferenceStatus
    {
        return $status instanceof GuideReferenceStatus ? $status : GuideReferenceStatus::tryFrom((string) $status);
    }

    /** Re-derives the status after the reference photos changed; a switched-off object stays disabled. */
    public function refreshStatus(): void
    {
        if (! $this->is_active) {
            return;
        }

        $status = $this->computedStatus();

        $this->forceFill([
            'status' => $status,
            'ready_at' => $status === GuideObjectStatus::Ready ? ($this->ready_at ?? now()) : null,
        ])->saveQuietly();
    }

    /**
     * Adds uploaded reference photos (storage paths) in one go.
     *
     * @param  list<string>  $paths
     */
    public function addReferenceImages(array $paths): int
    {
        foreach ($paths as $path) {
            $this->referenceImages()->create(['path' => $path]);
        }

        return count($paths);
    }
}
