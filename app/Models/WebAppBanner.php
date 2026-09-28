<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class WebAppBanner extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'subtitle', 'image_path', 'link_url', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function imageUrl(): string
    {
        return Storage::disk('public')->url($this->image_path);
    }
}
