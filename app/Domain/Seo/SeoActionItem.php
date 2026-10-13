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

    /**
     * Kept here rather than as inline @php/match in the action-queue
     * Blade view - a match expression inside an @php block in a
     * Livewire-tracked @foreach body breaks Livewire's wire:key loop
     * compiler ("unexpected token endforeach").
     */
    public function sourceLabel(): string
    {
        return match ($this->source) {
            'technical_issue' => 'مشکل تکنیکال',
            'keyword_suggestion' => 'پیشنهاد کلمه کلیدی',
            default => 'فرصت سئو',
        };
    }

    public function sourceColor(): string
    {
        return match ($this->source) {
            'technical_issue' => 'danger',
            'keyword_suggestion' => 'info',
            default => 'warning',
        };
    }
}
