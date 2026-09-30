<?php

namespace App\Models;

use App\Enums\SeoSearchType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoSearchTypeMetric extends Model
{
    use HasFactory;

    protected $fillable = ['seo_project_id', 'date', 'search_type', 'clicks', 'impressions', 'ctr', 'position'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'search_type' => SeoSearchType::class,
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
