<?php

namespace App\Models;

use App\Enums\DocumentAccessLevel;
use App\Models\Concerns\HasTags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Document extends Model
{
    use HasTags;

    protected $fillable = [
        'title', 'file_path', 'mime_type', 'size_kb',
        'owner_id', 'owner_type', 'access_level', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'access_level' => DocumentAccessLevel::class,
        ];
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
