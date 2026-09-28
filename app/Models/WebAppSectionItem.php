<?php

namespace App\Models;

use App\Enums\WebAppTileAccent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class WebAppSectionItem extends Model
{
    use HasFactory;

    protected $fillable = ['web_app_section_id', 'label', 'icon_path', 'accent', 'link_url', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'accent' => WebAppTileAccent::class,
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(WebAppSection::class, 'web_app_section_id');
    }

    public function iconUrl(): ?string
    {
        return $this->icon_path ? Storage::disk('public')->url($this->icon_path) : null;
    }
}
