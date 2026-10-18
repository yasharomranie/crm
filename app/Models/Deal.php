<?php

namespace App\Models;

use App\Domain\Crm\DealAutomationService;
use App\Domain\Crm\LeadScorer;
use App\Enums\DealSource;
use App\Enums\DealStage;
use App\Models\Concerns\HasFollowUpTasks;
use App\Models\Concerns\HasTags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Deal extends Model
{
    use HasFollowUpTasks, HasTags;

    protected $fillable = [
        'guest_id', 'title', 'stage', 'value', 'lead_score', 'assigned_to',
        'source', 'external_api_client_id', 'lead_capture_form_id', 'expected_close_date', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'stage' => DealStage::class,
            'source' => DealSource::class,
            'value' => 'decimal:0',
            'expected_close_date' => 'date',
            'stale_notified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (self $deal) {
            if ($deal->wasRecentlyCreated || $deal->wasChanged('stage')) {
                app(DealAutomationService::class)->handleStageEntered($deal);
            }

            // refresh() writes with a plain query, so this never re-triggers saved.
            app(LeadScorer::class)->refresh($deal);
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(DealItem::class);
    }

    /**
     * A deal with quote lines is worth exactly what its lines add up to;
     * one without lines keeps the value typed in by hand.
     */
    public function syncValueFromItems(): void
    {
        $items = $this->items()->get();

        if ($items->isEmpty()) {
            return;
        }

        $total = (int) $items->sum(fn (DealItem $item) => $item->lineTotal());

        if ((int) $this->value !== $total) {
            $this->update(['value' => $total]);
        }
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function leadCaptureForm(): BelongsTo
    {
        return $this->belongsTo(LeadCaptureForm::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
