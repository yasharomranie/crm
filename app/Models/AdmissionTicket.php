<?php

namespace App\Models;

use App\Enums\AdmissionTicketStatus;
use App\Enums\TicketSourceType;
use Database\Factories\AdmissionTicketFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Named AdmissionTicket, not Ticket - see the create_admission_tickets_table
 * migration's own doc comment for why (GuestTicket, a completely unrelated
 * customer-support ticket, already owns that name in this app).
 */
class AdmissionTicket extends Model
{
    /** @use HasFactory<AdmissionTicketFactory> */
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'used_uses', 'cancelled_at', 'cancellation_reason', 'blocked_reason'])
            ->logOnlyDirty()
            ->useLogName('admission_ticket');
    }

    protected $fillable = [
        'ticket_number', 'order_item_id', 'ticket_variant_id', 'status',
        'allowed_uses', 'used_uses', 'valid_from', 'valid_until', 'source_type',
        'ticket_template_version_id', 'customer_name', 'customer_phone', 'external_ticket_id',
        'cancelled_by', 'cancelled_at', 'cancellation_reason', 'blocked_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => AdmissionTicketStatus::class,
            'source_type' => TicketSourceType::class,
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $ticket) {
            // Human-readable, sequential-looking, but NOT the QR secret -
            // see QrToken. TKT-<Ymd>-<6 random digits>, per section 14.
            // Six random digits per day collide eventually on a busy day,
            // and ticket_number is unique - pick again instead of failing a sale.
            while (blank($ticket->ticket_number) || static::where('ticket_number', $ticket->ticket_number)->exists()) {
                $ticket->ticket_number = 'TKT-'.now()->format('ymd').'-'.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            }
        });
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function ticketVariant(): BelongsTo
    {
        return $this->belongsTo(TicketVariant::class);
    }

    public function ticketTemplateVersion(): BelongsTo
    {
        return $this->belongsTo(TicketTemplateVersion::class);
    }

    public function qrToken(): HasOne
    {
        return $this->hasOne(QrToken::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(TicketUsage::class)->orderBy('usage_number');
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(TicketUsageLog::class);
    }

    public function printLogs(): HasMany
    {
        return $this->hasMany(PrintLog::class);
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function getRemainingUsesAttribute(): int
    {
        return max(0, $this->allowed_uses - $this->used_uses);
    }

    /**
     * The usage stage a fresh scan would consume next (1-based). Gate
     * rules match against this, not against a raw "used/unused" boolean
     * (section 6/10) - a round trip's return leg only ever matches a
     * rule whose usage_stage is 2, so it's structurally impossible to
     * consume it before stage 1.
     */
    public function nextUsageNumber(): int
    {
        return $this->used_uses + 1;
    }

    public function isCurrentlyValid(?Carbon $at = null): bool
    {
        $at ??= Carbon::now();

        return $this->valid_from->lessThanOrEqualTo($at) && $this->valid_until->greaterThan($at);
    }

    public function isBlockingStatus(): bool
    {
        return in_array($this->status, [
            AdmissionTicketStatus::Cancelled,
            AdmissionTicketStatus::Refunded,
            AdmissionTicketStatus::Blocked,
        ], true);
    }
}
