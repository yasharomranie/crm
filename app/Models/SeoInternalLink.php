<?php

namespace App\Models;

use Database\Factories\SeoInternalLinkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One internal link edge (from_url -> to_url) discovered while
 * App\Domain\Seo\TechnicalSeoAuditor crawled a project's site - the raw
 * material for the audit's internal-link-graph checks (weakly-linked and
 * orphan pages). Deduplicated per audit: the same link repeated across
 * every page's nav/footer is recorded once, not once per occurrence, so
 * a page's incoming-link count reflects how many *distinct* pages link to
 * it rather than being inflated by shared site chrome.
 */
class SeoInternalLink extends Model
{
    /** @use HasFactory<SeoInternalLinkFactory> */
    use HasFactory;

    protected $fillable = ['seo_technical_audit_id', 'from_url', 'to_url'];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(SeoTechnicalAudit::class, 'seo_technical_audit_id');
    }
}
