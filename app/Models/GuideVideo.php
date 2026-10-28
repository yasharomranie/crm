<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** The story of an object: an uploaded file or a link (a CDN, an object store). */
class GuideVideo extends Model
{
    use HasFactory;

    protected $fillable = ['guide_object_id', 'title', 'path', 'url', 'poster_path', 'duration_seconds', 'language', 'is_published', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'duration_seconds' => 'integer',
        ];
    }

    public function object(): BelongsTo
    {
        return $this->belongsTo(GuideObject::class, 'guide_object_id');
    }

    /** The address a phone plays: the link when there is one, otherwise the uploaded file. */
    public function playUrl(): ?string
    {
        if (filled($this->url)) {
            return $this->url;
        }

        return filled($this->path) ? Storage::disk('public')->url($this->path) : null;
    }

    public function posterUrl(): ?string
    {
        return filled($this->poster_path) ? Storage::disk('public')->url($this->poster_path) : null;
    }
}
