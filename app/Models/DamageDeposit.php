<?php

namespace App\Models;

use App\Enums\BookingPaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The security deposit a guest leaves for possible damage (common for villas).
 * It is deliberately not part of the folio: it is not revenue, it does not
 * reduce what the guest owes, and it is either given back or - for the part
 * that paid for damage - kept, once, when the stay ends. Amounts are in toman.
 */
class DamageDeposit extends Model
{
    protected $table = 'booking_damage_deposits';

    protected $fillable = [
        'booking_id', 'held_amount', 'method', 'received_at', 'deducted_amount',
        'refunded_amount', 'settlement_note', 'settled_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'held_amount' => 'integer',
            'method' => BookingPaymentMethod::class,
            'received_at' => 'datetime',
            'deducted_amount' => 'integer',
            'refunded_amount' => 'integer',
            'settled_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function isSettled(): bool
    {
        return $this->settled_at !== null;
    }

    /**
     * Money the hotel holds right now: received and not yet settled.
     */
    public function outstandingAmount(): int
    {
        return $this->isSettled() ? 0 : $this->held_amount;
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->isSettled() => 'تسویه‌شده',
            $this->held_amount > 0 => 'نزد ما (تسویه‌نشده)',
            default => 'دریافت نشده',
        };
    }
}
