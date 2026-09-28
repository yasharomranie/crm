<?php

namespace App\Models;

use App\Enums\SeoConfidenceLevel;
use App\Enums\SeoOpportunityPriority;
use App\Enums\SeoOpportunityStatus;
use App\Enums\SeoOpportunityType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoOpportunity extends Model
{
    use HasFactory;

    protected $fillable = [
        'seo_project_id', 'type', 'title', 'subject_query', 'subject_page_url',
        'evidence', 'recommended_actions', 'priority', 'confidence', 'status',
        'detected_at', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => SeoOpportunityType::class,
            'evidence' => 'array',
            'recommended_actions' => 'array',
            'priority' => SeoOpportunityPriority::class,
            'confidence' => SeoConfidenceLevel::class,
            'status' => SeoOpportunityStatus::class,
            'detected_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoProject::class, 'seo_project_id');
    }
}
