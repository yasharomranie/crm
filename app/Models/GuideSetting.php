<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A singleton settings row (always id=1) for the smart tourism guide: the
 * on/off switch of the whole experiment and the numbers the visitor's phone
 * matches with. Off by default, so nothing reaches visitors until it is
 * switched on.
 */
class GuideSetting extends Model
{
    protected $fillable = ['is_enabled', 'similarity_threshold', 'min_margin', 'frame_interval_ms', 'min_reference_images'];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'similarity_threshold' => 'float',
            'min_margin' => 'float',
            'frame_interval_ms' => 'integer',
            'min_reference_images' => 'integer',
        ];
    }

    public static function current(): self
    {
        $setting = static::query()->firstOrCreate(['id' => 1]);

        // A row just created holds only what was passed in; reload it so the
        // column defaults (switched off, the starting numbers) are on the model.
        return $setting->wasRecentlyCreated ? $setting->refresh() : $setting;
    }
}
