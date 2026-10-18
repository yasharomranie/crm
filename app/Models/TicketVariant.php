<?php

namespace App\Models;

use App\Enums\AdmissionTicketStatus;
use App\Enums\TicketDirection;
use Database\Factories\TicketVariantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class TicketVariant extends Model
{
    /** @use HasFactory<TicketVariantFactory> */
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()->logOnlyDirty()->useLogName('ticket_variant');
    }

    protected $fillable = [
        'product_id', 'name', 'code', 'description', 'allowed_uses',
        'direction', 'default_validity_days', 'validity_end_of_day', 'daily_capacity',
        'requires_customer_info', 'ticket_template_id', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'direction' => TicketDirection::class,
            'is_active' => 'boolean',
            'validity_end_of_day' => 'boolean',
            'requires_customer_info' => 'boolean',
            'daily_capacity' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(TicketVariantPrice::class);
    }

    public function gateRules(): HasMany
    {
        return $this->hasMany(GateRule::class);
    }

    public function ticketTemplate(): BelongsTo
    {
        return $this->belongsTo(TicketTemplate::class);
    }

    /**
     * The price in effect right now. Never read this to fill in a
     * ticket/order-item's stored amount after issuance - those columns
     * are a permanent snapshot, exactly so a later price change (or
     * even deleting this row's current price) can never alter a ticket
     * that already exists (section 18-19's "non-negotiable" rule).
     */
    public function currentPrice(?Carbon $at = null): ?TicketVariantPrice
    {
        $at ??= Carbon::now();
        $dayType = in_array($at->dayOfWeek, config('ticketing.weekend_days', [4, 5]), true) ? 'weekend' : 'weekday';

        // A price marked for weekdays/weekends beats the general one on a
        // matching day; among equals the newest start date wins.
        return $this->prices()
            ->where('effective_from', '<=', $at)
            ->where(fn ($q) => $q->whereNull('effective_until')->orWhere('effective_until', '>', $at))
            ->get()
            ->filter(fn (TicketVariantPrice $price) => in_array($price->applies_to ?? 'all', ['all', $dayType], true))
            ->sortByDesc('effective_from')
            ->sortByDesc(fn (TicketVariantPrice $price) => ($price->applies_to ?? 'all') === 'all' ? 0 : 1)
            ->first();
    }

    /**
     * Tickets of this type sold since midnight that still count against
     * the daily sales limit (cancelled and refunded ones free their place).
     */
    public function soldToday(): int
    {
        return AdmissionTicket::query()
            ->where('ticket_variant_id', $this->id)
            ->where('created_at', '>=', now()->startOfDay())
            ->whereNotIn('status', [AdmissionTicketStatus::Cancelled, AdmissionTicketStatus::Refunded])
            ->count();
    }

    /**
     * @return int|null how many more can be sold today, null when there is no limit
     */
    public function remainingToday(): ?int
    {
        return $this->daily_capacity === null ? null : max(0, $this->daily_capacity - $this->soldToday());
    }
}
