<?php

namespace App\Models;

use App\Enums\SeoBusinessValue;
use App\Enums\SeoCannibalizationRisk;
use App\Enums\SeoKeywordTrend;
use App\Enums\SeoSearchIntent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoKeywordProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'seo_project_id', 'query', 'query_hash', 'current_position', 'previous_position',
        'best_position', 'worst_position', 'clicks_28d', 'impressions_28d', 'ctr_28d',
        'trend', 'landing_page', 'topic_cluster_id', 'business_value', 'opportunity_score',
        'cannibalization_risk', 'intent', 'last_analyzed_at', 'classified_at',
    ];

    protected function casts(): array
    {
        return [
            'current_position' => 'decimal:2',
            'previous_position' => 'decimal:2',
            'best_position' => 'decimal:2',
            'worst_position' => 'decimal:2',
            'clicks_28d' => 'integer',
            'impressions_28d' => 'integer',
            'ctr_28d' => 'decimal:4',
            'trend' => SeoKeywordTrend::class,
            'business_value' => SeoBusinessValue::class,
            'opportunity_score' => 'decimal:2',
            'cannibalization_risk' => SeoCannibalizationRisk::class,
            'intent' => SeoSearchIntent::class,
            'last_analyzed_at' => 'datetime',
            'classified_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoProject::class, 'seo_project_id');
    }

    public function topicCluster(): BelongsTo
    {
        return $this->belongsTo(SeoTopicCluster::class);
    }
}
