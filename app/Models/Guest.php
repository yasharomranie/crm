<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\LoyaltyTier;
use App\Enums\MessageChannel;
use App\Enums\WalletTransactionType;
use App\Exceptions\InsufficientWalletBalanceException;
use App\Models\Concerns\HasFollowUpTasks;
use App\Models\Concerns\HasTags;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Doubles as the customer-app's authenticatable identity (see config/auth.php's
 * "customer" guard) on top of its original role as the CRM's guest record -
 * one row per person, so a wallet/loyalty history created by staff from the
 * admin panel is the exact same history the person sees after logging into
 * the customer app with that same mobile number.
 */
class Guest extends Authenticatable
{
    use HasFactory, HasFollowUpTasks, HasTags, LogsActivity;

    protected $fillable = ['name', 'national_code', 'mobile', 'email', 'password', 'google_id', 'mobile_verified_at', 'notes', 'wallet_balance'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'wallet_balance' => 'decimal:0',
            'password' => 'hashed',
            'mobile_verified_at' => 'datetime',
        ];
    }

    /**
     * Excludes wallet_balance (already has its own ledger in walletTransactions)
     * and system-managed fields (password, google_id, mobile_verified_at) that
     * would just add noise to the guest's activity timeline.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'national_code', 'mobile', 'email', 'notes'])
            ->logOnlyDirty()
            ->useLogName('guest');
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

    public function consents(): HasMany
    {
        return $this->hasMany(GuestConsent::class);
    }

    /**
     * Whether the guest's latest answer for this channel is "yes" to
     * promotional messages. No answer at all means no consent.
     */
    public function hasMarketingConsent(MessageChannel $channel): bool
    {
        return $this->consents()->where('channel', $channel->value)->orderByDesc('id')->value('status') === GuestConsent::STATUS_GRANTED;
    }

    /**
     * Appends a consent decision - only when it differs from the current
     * answer (no answer counts as "no"), so repeated form submissions
     * don't pad the history.
     */
    public function recordConsent(MessageChannel $channel, bool $granted, string $source, ?string $note = null, ?int $recordedBy = null, ?string $ip = null): ?GuestConsent
    {
        if ($this->hasMarketingConsent($channel) === $granted) {
            return null;
        }

        return $this->consents()->create([
            'channel' => $channel,
            'status' => $granted ? GuestConsent::STATUS_GRANTED : GuestConsent::STATUS_REVOKED,
            'source' => $source,
            'note' => $note,
            'ip_address' => $ip,
            'recorded_by' => $recordedBy,
        ]);
    }

    public function customerMessages(): HasMany
    {
        return $this->hasMany(CustomerMessage::class);
    }

    public function tripPlans(): HasMany
    {
        return $this->hasMany(TripPlan::class);
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
        return static::tierFor($this->frequency(), $this->monetaryTotal());
    }

    /**
     * The tier thresholds in one place, so reports that already hold the
     * numbers (stays, lifetime spend) classify guests exactly as the guest
     * page does.
     */
    public static function tierFor(int $frequency, int $monetary): LoyaltyTier
    {
        return match (true) {
            $frequency >= 5 && $monetary >= 100_000_000 => LoyaltyTier::Vip,
            $frequency >= 3 || $monetary >= 50_000_000 => LoyaltyTier::Gold,
            $frequency >= 1 => LoyaltyTier::Silver,
            default => LoyaltyTier::Bronze,
        };
    }

    /**
     * Every wallet change goes through creditWallet()/debitWallet(): the
     * guest row is locked, the ledger row and the cached wallet_balance are
     * written in one database transaction, and a debit can never take the
     * balance below zero - so two simultaneous spends cannot both succeed
     * on the same money, and the ledger always adds up to the balance.
     */
    public function creditWallet(int $amount, string $reason, ?Booking $booking = null, ?CarbonInterface $expiresAt = null): WalletTransaction
    {
        return $this->applyWalletChange(WalletTransactionType::Credit, $amount, $reason, $booking, ['expires_at' => $expiresAt]);
    }

    /**
     * @throws InsufficientWalletBalanceException
     */
    public function debitWallet(int $amount, string $reason, ?Booking $booking = null, ?int $sourceTransactionId = null): WalletTransaction
    {
        return $this->applyWalletChange(WalletTransactionType::Debit, $amount, $reason, $booking, ['source_transaction_id' => $sourceTransactionId]);
    }

    /**
     * Sum of the ledger (credits minus debits). Should always equal
     * wallet_balance; a difference means the balance was changed outside
     * creditWallet()/debitWallet().
     */
    public function walletLedgerBalance(): int
    {
        $credits = (int) $this->walletTransactions()->where('type', WalletTransactionType::Credit)->sum('amount');
        $debits = (int) $this->walletTransactions()->where('type', WalletTransactionType::Debit)->sum('amount');

        return $credits - $debits;
    }

    /**
     * @param  array<string, mixed>  $extra  additional wallet_transactions columns
     */
    private function applyWalletChange(WalletTransactionType $type, int $amount, string $reason, ?Booking $booking, array $extra = []): WalletTransaction
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('مبلغ تراکنش کیف پول باید بیشتر از صفر باشد.');
        }

        return DB::transaction(function () use ($type, $amount, $reason, $booking, $extra) {
            $locked = static::query()->lockForUpdate()->findOrFail($this->getKey());
            $currentBalance = (int) $locked->wallet_balance;

            if ($type === WalletTransactionType::Debit && $currentBalance < $amount) {
                throw new InsufficientWalletBalanceException($currentBalance, $amount);
            }

            $transaction = $locked->walletTransactions()->create([
                'booking_id' => $booking?->id,
                'type' => $type,
                'amount' => $amount,
                'reason' => $reason,
                'created_by' => Auth::guard('web')->id(),
                ...$extra,
            ]);

            $newBalance = $type === WalletTransactionType::Credit ? $currentBalance + $amount : $currentBalance - $amount;
            $locked->forceFill(['wallet_balance' => $newBalance])->saveQuietly();
            $this->setAttribute('wallet_balance', $newBalance);
            $this->syncOriginalAttribute('wallet_balance');

            return $transaction;
        });
    }
}
