<?php

namespace App\Models;

use App\Enums\SeoOpportunityOutcomeVerdict;
use Database\Factories\SeoOpportunityOutcomeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The "learning" step of مرکز سئو's data -> analysis -> opportunity ->
 * action -> measurement -> learning loop: whenever an opportunity is
 * marked resolved, App\Domain\Seo\OpportunityOutcomeService captures a
 * baseline here, then re-measures the same metric ~28 days later to
 * record whether the fix actually worked - never assumed, always
 * checked against the warehouse.
 */
class SeoOpportunityOutcome extends Model
{
    /** @use HasFactory<SeoOpportunityOutcomeFactory> */
    use HasFactory;

    protected $fillable = [
        'seo_opportunity_id', 'baseline_captured_at', 'baseline_clicks', 'baseline_impressions', 'baseline_position',
        'followup_measured_at', 'followup_clicks', 'followup_impressions', 'followup_position', 'verdict',
    ];

    protected function casts(): array
    {
        return [
            'baseline_captured_at' => 'datetime',
            'baseline_clicks' => 'integer',
            'baseline_impressions' => 'integer',
            'baseline_position' => 'decimal:2',
            'followup_measured_at' => 'datetime',
            'followup_clicks' => 'integer',
            'followup_impressions' => 'integer',
            'followup_position' => 'decimal:2',
            'verdict' => SeoOpportunityOutcomeVerdict::class,
        ];
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(SeoOpportunity::class, 'seo_opportunity_id');
    }
}
