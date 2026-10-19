<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A link the front desk sends a guest to pay (part of) a booking online.
 * The guest opens it without logging in; the token is the only key, so it is
 * long and random, expires, and works for one successful payment.
 * amount is in toman.
 */
class BookingPaymentLink extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const VALID_DAYS = 3;

    protected $fillable = ['booking_id', 'token', 'amount', 'authority', 'status', 'ref_id', 'card_pan', 'error', 'paid_at', 'expires_at', 'created_by'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $link) {
            $link->token ??= Str::random(40);
            $link->expires_at ??= now()->addDays(self::VALID_DAYS);
        });
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function url(): string
    {
        return route('booking-pay.show', $this->token);
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_PENDING && $this->expires_at->isPast();
    }

    /**
     * Can the guest still pay with this link?
     */
    public function isOpen(): bool
    {
        return $this->status === self::STATUS_PENDING && ! $this->expires_at->isPast();
    }
}
