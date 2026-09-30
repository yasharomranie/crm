<?php

namespace App\Models;

use App\Enums\SeoTechnicalAuditStatus;
use Database\Factories\SeoTechnicalAuditFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One run of App\Domain\Seo\TechnicalSeoAuditor over a project's own site -
 * a self-built crawler (no paid third-party API), so unlike the rank
 * tracker this has no external quota to worry about.
 */
class SeoTechnicalAudit extends Model
{
    /** @use HasFactory<SeoTechnicalAuditFactory> */
    use HasFactory;

    protected $fillable = ['seo_project_id', 'status', 'score', 'pages_scanned', 'started_at', 'completed_at', 'performance_score', 'core_web_vitals', 'performance_error'];

    protected function casts(): array
    {
        return [
            'status' => SeoTechnicalAuditStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'core_web_vitals' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoProject::class, 'seo_project_id');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(SeoTechnicalAuditIssue::class);
    }
}
