<?php

namespace App\Models;

use App\Enums\GuideEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One anonymous outcome reported by a visitor's phone: what was recognised and how fast, never who or a picture. */
class GuideEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['guide_object_id', 'type', 'confidence', 'latency_ms'];

    protected function casts(): array
    {
        return [
            'type' => GuideEventType::class,
            'confidence' => 'float',
            'latency_ms' => 'integer',
        ];
    }

    public function object(): BelongsTo
    {
        return $this->belongsTo(GuideObject::class, 'guide_object_id');
    }
}
