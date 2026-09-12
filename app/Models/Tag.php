<?php

namespace App\Models;

use App\Enums\TagVisibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    protected $fillable = ['parent_id', 'name', 'slug', 'color_hex', 'visibility', 'owner_id'];

    protected function casts(): array
    {
        return [
            'visibility' => TagVisibility::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $tag) {
            $tag->slug ??= Str::slug($tag->name) ?: (string) Str::uuid();
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
