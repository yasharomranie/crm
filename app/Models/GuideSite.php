<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** A tourism complex the guide runs in (today: گنجنامه). Kept as a table so more sites can follow later. */
class GuideSite extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $site): void {
            if (blank($site->slug)) {
                $site->slug = Str::slug($site->name) ?: 'site-'.Str::lower(Str::random(6));
            }
        });
    }

    public function zones(): HasMany
    {
        return $this->hasMany(GuideZone::class);
    }

    public function objects(): HasMany
    {
        return $this->hasMany(GuideObject::class);
    }
}
