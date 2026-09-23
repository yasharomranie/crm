<?php

namespace App\Models;

use App\Enums\TicketDirection;
use Database\Factories\GateRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class GateRule extends Model
{
    /** @use HasFactory<GateRuleFactory> */
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty()->useLogName('gate_rule');
    }

    protected $fillable = ['gate_id', 'product_id', 'ticket_variant_id', 'direction', 'usage_stage', 'is_active'];

    protected function casts(): array
    {
        return [
            'direction' => TicketDirection::class,
            'is_active' => 'boolean',
        ];
    }

    public function gate(): BelongsTo
    {
        return $this->belongsTo(Gate::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function ticketVariant(): BelongsTo
    {
        return $this->belongsTo(TicketVariant::class);
    }

    /**
     * Whether this rule permits scanning $ticket at usage stage
     * $nextUsageNumber (1-based: the ticket's next, not-yet-consumed
     * usage) in direction $direction. Every constraint on the rule is
     * optional (null = "don't care") except is_active.
     */
    public function matches(AdmissionTicket $ticket, int $nextUsageNumber, ?TicketDirection $direction): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->product_id && $this->product_id !== $ticket->ticketVariant->product_id) {
            return false;
        }

        if ($this->ticket_variant_id && $this->ticket_variant_id !== $ticket->ticket_variant_id) {
            return false;
        }

        if ($this->direction && $this->direction !== $direction) {
            return false;
        }

        if ($this->usage_stage && $this->usage_stage !== $nextUsageNumber) {
            return false;
        }

        return true;
    }
}
