<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A part of a site (a cave hall, a gallery...) that objects are placed in. */
class GuideZone extends Model
{
    use HasFactory;

    protected $fillable = ['guide_site_id', 'name', 'sort_order'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(GuideSite::class, 'guide_site_id');
    }

    public function objects(): HasMany
    {
        return $this->hasMany(GuideObject::class);
    }
}
