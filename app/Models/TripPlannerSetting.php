<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A singleton settings row (always id=1), mirroring CustomerAppAuthSetting -
 * assistant_note is free text an admin can edit any time ("این هفته جاده
 * غار علی‌صدر بسته است", "جشنواره گل در باغ‌های شهر") that is injected
 * directly into every itinerary prompt by TripPlannerService, so staff can
 * steer what the AI suggests without a deploy. is_enabled is a kill switch
 * if the AI provider misbehaves or needs to be paused.
 */
class TripPlannerSetting extends Model
{
    protected $fillable = ['assistant_note', 'is_enabled'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }

    /**
     * See CustomerAppAuthSetting::current()'s own doc comment for why the
     * defaults are passed explicitly here rather than relying on the
     * is_enabled column's DB-level default.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1], [
            'assistant_note' => null,
            'is_enabled' => true,
        ]);
    }
}
