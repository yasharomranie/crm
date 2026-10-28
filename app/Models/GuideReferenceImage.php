<?php

namespace App\Models;

use App\Enums\GuideReferenceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * One photo of an object from one angle. Its "embedding" is the numeric
 * fingerprint the visitor's phone compares camera frames against; it is made
 * in the browser (never on the server) and stored here already normalised.
 */
class GuideReferenceImage extends Model
{
    use HasFactory;

    /** Longest fingerprint accepted: far above any model this guide is meant to use. */
    public const MAX_DIMENSIONS = 2048;

    protected $fillable = ['guide_object_id', 'path', 'embedding', 'status', 'error', 'processed_at'];

    protected function casts(): array
    {
        return [
            'embedding' => 'array',
            'status' => GuideReferenceStatus::class,
            'processed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        $refresh = fn (self $image) => $image->object?->refreshStatus();

        static::saved($refresh);
        static::deleted($refresh);
    }

    public function object(): BelongsTo
    {
        return $this->belongsTo(GuideObject::class, 'guide_object_id');
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    /**
     * Stores the fingerprint the browser computed (scaled to length 1).
     *
     * @param  list<float|int>  $vector
     */
    public function markProcessed(array $vector): void
    {
        $count = count($vector);

        if ($count < 1 || $count > self::MAX_DIMENSIONS) {
            throw new InvalidArgumentException('طول بردار ویژگی معتبر نیست.');
        }

        $norm = sqrt(array_sum(array_map(fn ($value) => (float) $value ** 2, $vector)));

        if ($norm <= 0.0 || is_nan($norm)) {
            throw new InvalidArgumentException('بردار ویژگی خالی است.');
        }

        $this->update([
            'embedding' => array_map(fn ($value) => round((float) $value / $norm, 5), $vector),
            'status' => GuideReferenceStatus::Processed,
            'error' => null,
            'processed_at' => now(),
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update(['embedding' => null, 'status' => GuideReferenceStatus::Failed, 'error' => mb_substr($error, 0, 500), 'processed_at' => now()]);
    }

    /** Sends the photo back to the queue, for example after the model changed. */
    public function reprocess(): void
    {
        $this->update(['embedding' => null, 'status' => GuideReferenceStatus::Pending, 'error' => null, 'processed_at' => null]);
    }
}
