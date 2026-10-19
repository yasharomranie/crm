<?php

namespace App\Models;

use App\Enums\BookingPaymentKind;
use App\Enums\BookingPaymentMethod;
use Database\Factories\BookingPaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of the money ledger of a booking: what the guest paid at the
 * desk (cash, card, transfer, online), an advance paid to hold the booking,
 * or money given back. Lines are never edited or deleted - a mistake is
 * corrected with a refund - so the ledger stays an honest record.
 * Payments out of the guest's wallet are tracked on the booking itself
 * (Booking::$wallet_paid_amount), not here.
 */
class BookingPayment extends Model
{
    /** @use HasFactory<BookingPaymentFactory> */
    use HasFactory;

    protected $fillable = ['booking_id', 'kind', 'method', 'amount', 'paid_at', 'reference', 'note', 'created_by'];

    protected function casts(): array
    {
        return [
            'kind' => BookingPaymentKind::class,
            'method' => BookingPaymentMethod::class,
            'amount' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * What this line does to the money received: refunds take away.
     */
    public function signedAmount(): int
    {
        return $this->kind === BookingPaymentKind::Refund ? -$this->amount : $this->amount;
    }
}
