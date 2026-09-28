<?php

namespace App\Models;

use App\Enums\SeoTechnicalIssueSeverity;
use App\Enums\SeoTechnicalIssueType;
use Database\Factories\SeoTechnicalAuditIssueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoTechnicalAuditIssue extends Model
{
    /** @use HasFactory<SeoTechnicalAuditIssueFactory> */
    use HasFactory;

    protected $fillable = ['seo_technical_audit_id', 'type', 'severity', 'url', 'details'];

    protected function casts(): array
    {
        return [
            'type' => SeoTechnicalIssueType::class,
            'severity' => SeoTechnicalIssueSeverity::class,
        ];
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(SeoTechnicalAudit::class, 'seo_technical_audit_id');
    }
}
