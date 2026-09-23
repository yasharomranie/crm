<?php

namespace App\Models;

use App\Enums\TicketDirection;
use Database\Factories\TicketVariantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class TicketVariant extends Model
{
    /** @use HasFactory<TicketVariantFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id', 'name', 'code', 'description', 'allowed_uses',
        'direction', 'default_validity_days', 'ticket_template_id',
        'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'direction' => TicketDirection::class,
            'is_active' => 'boolean',
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

        return $this->prices()
            ->where('effective_from', '<=', $at)
            ->where(fn ($q) => $q->whereNull('effective_until')->orWhere('effective_until', '>', $at))
            ->orderByDesc('effective_from')
            ->first();
    }
}
