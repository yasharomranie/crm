<?php

namespace App\Models;

use App\Enums\SeoContentItemStatus;
use Database\Factories\SeoContentItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The "action" step of مرکز سئو's data -> analysis -> opportunity ->
 * action -> measurement -> learning loop: a plannable, assignable content
 * task, optionally traced back to whichever SeoOpportunity or
 * SeoKeywordSuggestion prompted it (both nullable - a content item can
 * also start from a plain idea with no upstream source).
 */
class SeoContentItem extends Model
{
    /** @use HasFactory<SeoContentItemFactory> */
    use HasFactory;

    protected $fillable = [
        'seo_project_id', 'title', 'target_keyword', 'keyword_profile_id',
        'keyword_suggestion_id', 'seo_opportunity_id', 'content_type', 'status',
        'assigned_to', 'due_date', 'published_url', 'published_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => SeoContentItemStatus::class,
            'due_date' => 'date',
            'published_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoProject::class, 'seo_project_id');
    }

    public function keywordProfile(): BelongsTo
    {
        return $this->belongsTo(SeoKeywordProfile::class, 'keyword_profile_id');
    }

    public function keywordSuggestion(): BelongsTo
    {
        return $this->belongsTo(SeoKeywordSuggestion::class, 'keyword_suggestion_id');
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(SeoOpportunity::class, 'seo_opportunity_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
