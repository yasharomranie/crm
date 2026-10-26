<?php

namespace App\Models;

use App\Enums\DigitalProjectChannel;
use App\Enums\DigitalProjectStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One digital asset the organisation runs (a website, an Instagram page, a
 * Telegram channel, a LinkedIn page, a WhatsApp page...), kept as a short
 * brief: what it is, where it stands, who owns it and when it is next
 * reviewed. Distinct from Project (a budgeted project with phases) and from
 * SeoProject (the SEO work of one website).
 */
class DigitalProject extends Model
{
    use HasFactory;

    protected $fillable = ['channel', 'name', 'handle', 'description', 'status', 'owner_id', 'next_review_at', 'created_by'];

    protected function casts(): array
    {
        return [
            'channel' => DigitalProjectChannel::class,
            'status' => DigitalProjectStatus::class,
            'next_review_at' => 'date',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Projects still being worked on whose review date has passed. */
    public function scopeReviewOverdue(Builder $query): Builder
    {
        return $query
            ->whereNotNull('next_review_at')
            ->whereDate('next_review_at', '<', today())
            ->whereIn('status', DigitalProjectStatus::openValues());
    }

    public function isReviewOverdue(): bool
    {
        return $this->status->isOpen() && $this->next_review_at !== null && $this->next_review_at->lt(today());
    }
}
