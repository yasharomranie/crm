<?php

namespace App\Models;

use App\Enums\SeoKeywordSuggestionStatus;
use App\Enums\SeoSearchIntent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoKeywordSuggestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'seo_project_id', 'keyword', 'intent', 'topic_cluster_id',
        'suggested_content_type', 'reason', 'status',
    ];

    protected function casts(): array
    {
        return [
            'intent' => SeoSearchIntent::class,
            'status' => SeoKeywordSuggestionStatus::class,
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
