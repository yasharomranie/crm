<?php

namespace App\Domain\Seo;

use Illuminate\Support\Carbon;

/**
 * One row in the unified action queue (see SeoActionQueueService) - a
 * read-only, in-memory view over whatever table actually owns the
 * underlying record (SeoOpportunity, SeoTechnicalAuditIssue, and future
 * sources like content gaps), so none of those tables need a shared
 * schema or a new table to duplicate their state into.
 */
final readonly class SeoActionItem
{
    public function __construct(
        public string $source,
        public string $title,
        public ?string $detail,
        public ?string $url,
        public float $score,
        public Carbon $detectedAt,
    ) {}
}
