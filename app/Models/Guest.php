<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\LoyaltyTier;
use App\Enums\WalletTransactionType;
use App\Models\Concerns\HasTags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Guest extends Model
{
    use HasTags;

    protected $fillable = ['name', 'national_code', 'mobile', 'email', 'notes', 'wallet_balance'];

    protected function casts(): array
    {
        return [
            'wallet_balance' => 'decimal:0',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(GuestFeedback::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(GuestTicket::class);
    }

    /**
     * Completed stays only - a booking that hasn't checked out yet
     * shouldn't count toward RFM.
     */
    private function completedBookings()
    {
        return $this->bookings()->where('status', BookingStatus::CheckedOut);
    }

    /**
     * R - days since the guest's most recent completed stay. Null if
     * they've never completed one.
     */
    public function recencyDays(): ?int
    {
        $lastCheckout = $this->completedBookings()->max('actual_check_out_at');

        return $lastCheckout ? now()->diffInDays($lastCheckout) : null;
    }

    /**
     * F - number of completed stays.
     */
    public function frequency(): int
    {
        return $this->completedBookings()->count();
    }

    /**
     * M - lifetime value across completed stays (sum of each booking's
     * folio total).
     */
    public function monetaryTotal(): int
    {
        return (int) $this->completedBookings()
            ->with('charges')
            ->get()
            ->sum(fn (Booking $booking) => $booking->totalAmount());
    }

    /**
     * A simple RFM-based tier. Real thresholds should be tuned once there's
     * real transaction history; these are a reasonable starting point.
     */
    public function loyaltyTier(): LoyaltyTier
    {
        $frequency = $this->frequency();
        $monetary = $this->monetaryTotal();

        return match (true) {
            $frequency >= 5 && $monetary >= 100_000_000 => LoyaltyTier::Vip,
            $frequency >= 3 || $monetary >= 50_000_000 => LoyaltyTier::Gold,
            $frequency >= 1 => LoyaltyTier::Silver,
            default => LoyaltyTier::Bronze,
        };
    }

    public function creditWallet(int $amount, string $reason, ?Booking $booking = null): WalletTransaction
    {
        $transaction = $this->walletTransactions()->create([
            'booking_id' => $booking?->id,
            'type' => WalletTransactionType::Credit,
            'amount' => $amount,
            'reason' => $reason,
        ]);

        $this->increment('wallet_balance', $amount);

        return $transaction;
    }

    public function debitWallet(int $amount, string $reason, ?Booking $booking = null): WalletTransaction
    {
        $transaction = $this->walletTransactions()->create([
            'booking_id' => $booking?->id,
            'type' => WalletTransactionType::Debit,
            'amount' => $amount,
            'reason' => $reason,
        ]);

        $this->decrement('wallet_balance', $amount);

        return $transaction;
    }
}
