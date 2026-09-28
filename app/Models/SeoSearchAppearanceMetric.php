<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoSearchAppearanceMetric extends Model
{
    use HasFactory;

    protected $fillable = ['seo_project_id', 'date', 'search_appearance', 'clicks', 'impressions', 'ctr', 'position'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'clicks' => 'integer',
            'impressions' => 'integer',
            'ctr' => 'decimal:4',
            'position' => 'decimal:2',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoProject::class, 'seo_project_id');
    }
}
