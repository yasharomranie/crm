<?php

namespace App\Models;

use App\Enums\BookingPaymentKind;
use App\Enums\BookingPaymentMethod;
use App\Enums\BookingPaymentStatus;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Exceptions\InsufficientWalletBalanceException;
use App\Exceptions\RoomNotAvailableException;
use App\Models\Concerns\HasTags;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class Booking extends Model
{
    use HasFactory, HasTags;

    protected $fillable = [
        'reference_code', 'room_id', 'guest_id', 'check_in_date', 'check_out_date',
        'actual_check_in_at', 'actual_check_out_at', 'adults_count', 'children_count',
        'status', 'source', 'sales_channel_id', 'external_api_client_id', 'commission_amount', 'wallet_paid_amount', 'notes', 'created_by',
        'cancellation_policy_id', 'cancellation_terms', 'cancelled_at', 'cancellation_kind', 'cancellation_reason', 'cancellation_fee',
    ];

    public const CANCELLED_BY_GUEST = 'guest';

    public const CANCELLED_NO_SHOW = 'no_show';

    protected function casts(): array
    {
        return [
            'check_in_date' => 'date',
            'check_out_date' => 'date',
            'actual_check_in_at' => 'datetime',
            'actual_check_out_at' => 'datetime',
            'status' => BookingStatus::class,
            'source' => BookingSource::class,
            'commission_amount' => 'decimal:0',
            'wallet_paid_amount' => 'decimal:0',
            'cancellation_terms' => 'array',
            'cancelled_at' => 'datetime',
            'cancellation_fee' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $booking) {
            $booking->reference_code ??= static::generateReferenceCode();

            // The cancellation terms are copied now, so a later change of the
            // policy never changes what this guest was promised.
            if (blank($booking->cancellation_terms)) {
                $policy = $booking->cancellation_policy_id
                    ? CancellationPolicy::find($booking->cancellation_policy_id)
                    : CancellationPolicy::defaultPolicy();

                $booking->cancellation_terms = $policy?->terms();
                $booking->cancellation_policy_id ??= $policy?->id;
            }

            // Best-effort guard for direct ::create() calls (tinker, relation
            // managers, seeders). The authoritative, concurrency-safe check
            // is CreateBooking::handleRecordCreation(), which wraps this in
            // a DB transaction with a row lock on the room.
            static::assertRoomAvailable(
                Room::findOrFail($booking->room_id),
                $booking->check_in_date instanceof Carbon ? $booking->check_in_date : Carbon::parse($booking->check_in_date),
                $booking->check_out_date instanceof Carbon ? $booking->check_out_date : Carbon::parse($booking->check_out_date),
            );
        });

        static::updating(function (self $booking) {
            if ($booking->isDirty('status')) {
                if ($booking->status === BookingStatus::Cancelled && $booking->cancelled_at === null) {
                    // Cancelled by editing the status (not through cancel()):
                    // the policy's fee for today applies.
                    $booking->cancelled_at = now();
                    $booking->cancellation_kind ??= self::CANCELLED_BY_GUEST;
                    $booking->cancellation_fee = $booking->cancellationFeeFor($booking->cancellation_kind === self::CANCELLED_NO_SHOW);
                } elseif ($booking->status !== BookingStatus::Cancelled && $booking->getOriginal('status') === BookingStatus::Cancelled) {
                    $booking->cancelled_at = null;
                    $booking->cancellation_kind = null;
                    $booking->cancellation_reason = null;
                    $booking->cancellation_fee = 0;
                }
            }

            // A booking being cancelled frees the room, so it can never
            // conflict with anything - skip the check entirely.
            if ($booking->status === BookingStatus::Cancelled) {
                return;
            }

            // Only worth checking when something that affects room
            // availability actually changed (dates, room, or a status
            // change that re-activates a previously cancelled booking).
            if (! $booking->isDirty(['room_id', 'check_in_date', 'check_out_date', 'status'])) {
                return;
            }

            // Best-effort guard, mirroring the `creating` hook above. The
            // authoritative, concurrency-safe check is
            // EditBooking::handleRecordUpdate(), which wraps this in a DB
            // transaction with a row lock on the room.
            static::assertRoomAvailable(
                Room::findOrFail($booking->room_id),
                $booking->check_in_date instanceof Carbon ? $booking->check_in_date : Carbon::parse($booking->check_in_date),
                $booking->check_out_date instanceof Carbon ? $booking->check_out_date : Carbon::parse($booking->check_out_date),
                $booking->id,
            );
        });

        static::updated(function (self $booking) {
            if ($booking->wasChanged('status') && $booking->status === BookingStatus::Cancelled) {
                $booking->returnWalletMoneyAfterCancellation();
            }
        });

        static::created(function (self $booking) {
            $totalForStay = $booking->room->roomType->effectivePriceForRange(
                $booking->check_in_date,
                $booking->check_out_date,
            );

            $booking->charges()->create([
                'title' => "اقامت {$booking->nights()} شب",
                'quantity' => 1,
                'unit_amount' => $totalForStay,
            ]);
        });
    }

    /**
     * Overbooking prevention (doc2 "Channel Manager"): a room cannot have
     * two overlapping, non-cancelled bookings. Date ranges overlap when one
     * starts before the other ends on both sides (checkout day itself is
     * free, matching nights()'s exclusive end date).
     */
    public static function assertRoomAvailable(Room $room, Carbon $checkIn, Carbon $checkOut, ?int $excludeBookingId = null): void
    {
        $overlaps = static::query()
            ->where('room_id', $room->id)
            ->where('status', '!=', BookingStatus::Cancelled)
            ->when($excludeBookingId, fn ($query) => $query->whereKeyNot($excludeBookingId))
            ->where('check_in_date', '<', $checkOut)
            ->where('check_out_date', '>', $checkIn)
            ->exists();

        if ($overlaps) {
            throw RoomNotAvailableException::forOverlap($room->name);
        }
    }

    public static function generateReferenceCode(): string
    {
        $sequence = str_pad((string) (static::max('id') + 1), 6, '0', STR_PAD_LEFT);

        return "GNJ-BKG-{$sequence}";
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(SalesChannel::class, 'sales_channel_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function charges(): HasMany
    {
        return $this->hasMany(BookingCharge::class);
    }

    public function cancellationPolicy(): BelongsTo
    {
        return $this->belongsTo(CancellationPolicy::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BookingPayment::class);
    }

    public function housekeepingTasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'source');
    }

    public function nights(): int
    {
        return max(1, $this->check_in_date->diffInDays($this->check_out_date));
    }

    public function totalAmount(): int
    {
        return (int) $this->charges->sum(fn (BookingCharge $charge) => $charge->quantity * $charge->unit_amount);
    }

    /**
     * Money received at the desk or online (payments and advances, minus
     * refunds) - everything except the wallet, which has its own column.
     */
    public function receivedAmount(): int
    {
        return (int) $this->payments->sum(fn (BookingPayment $payment) => $payment->signedAmount());
    }

    /**
     * Everything paid so far: the desk/online ledger plus what came out of
     * the guest's wallet.
     */
    public function paidAmount(): int
    {
        return (int) $this->wallet_paid_amount + $this->receivedAmount();
    }

    /**
     * What is still unpaid on the folio.
     */
    public function balanceDue(): int
    {
        return max(0, $this->amountOwed() - $this->paidAmount());
    }

    /**
     * What the guest owes: the whole folio, or - once the booking is
     * cancelled - only the cancellation fee.
     */
    public function amountOwed(): int
    {
        return $this->status === BookingStatus::Cancelled ? (int) $this->cancellation_fee : $this->totalAmount();
    }

    /**
     * Cash the hotel has to hand back after a cancellation: what was received
     * at the desk or online beyond the cancellation fee. (The fee is taken
     * from that money first; wallet money is returned to the wallet.)
     */
    public function refundableAmount(): int
    {
        return $this->status === BookingStatus::Cancelled ? max(0, $this->receivedAmount() - (int) $this->cancellation_fee) : 0;
    }

    public function paymentStatus(): BookingPaymentStatus
    {
        $total = $this->amountOwed();
        $paid = $this->paidAmount();

        return match (true) {
            $paid > $total => BookingPaymentStatus::Overpaid,
            $paid >= $total => BookingPaymentStatus::Paid,
            $paid > 0 => BookingPaymentStatus::Partial,
            default => BookingPaymentStatus::Unpaid,
        };
    }

    /**
     * What the guest's wallet may still be asked to pay: the unpaid balance
     * of the folio, after the wallet and the desk payments.
     */
    public function walletPayableAmount(): int
    {
        return $this->balanceDue();
    }

    /**
     * Writes one line of the money ledger. A payment or advance can never
     * exceed what is still unpaid (on a cancelled booking that is only the
     * cancellation fee); a refund can never exceed the money actually received (wallet
     * payments are not refunded this way). The check and the write share a
     * transaction with a row lock, so two clerks cannot both overpay.
     *
     * @throws InvalidArgumentException
     */
    public function recordPayment(
        int $amount,
        BookingPaymentMethod $method,
        BookingPaymentKind $kind = BookingPaymentKind::Payment,
        ?string $reference = null,
        ?string $note = null,
        ?int $userId = null,
    ): BookingPayment {
        if ($amount <= 0) {
            throw new InvalidArgumentException('مبلغ باید بیشتر از صفر باشد.');
        }

        return DB::transaction(function () use ($amount, $method, $kind, $reference, $note, $userId) {
            $locked = static::query()->lockForUpdate()->with(['charges', 'payments'])->findOrFail($this->getKey());

            if ($kind === BookingPaymentKind::Refund) {
                if ($amount > $locked->receivedAmount()) {
                    throw new InvalidArgumentException('مبلغ استرداد از پولِ دریافت‌شده (نقد، کارت، حواله، آنلاین) بیشتر است؛ پرداخت از کیف پول از این راه برگردانده نمی‌شود.');
                }
            } else {
                if ($amount > $locked->balanceDue()) {
                    throw new InvalidArgumentException('مبلغ از مانده‌ی صورت‌حساب ('.number_format($locked->balanceDue()).' تومان) بیشتر است.');
                }
            }

            $payment = $locked->payments()->create([
                'kind' => $kind,
                'method' => $method,
                'amount' => $amount,
                'paid_at' => now(),
                'reference' => $reference,
                'note' => $note,
                'created_by' => $userId,
            ]);

            $this->unsetRelation('payments');

            return $payment;
        });
    }

    /**
     * Pays part (or all) of the folio from the guest's wallet. The debit
     * and the folio's wallet_paid_amount move together or not at all, and
     * the amount can never exceed what is still payable or the balance.
     *
     * @throws InsufficientWalletBalanceException
     * @throws InvalidArgumentException
     */
    public function payFromWallet(int $amount): WalletTransaction
    {
        if (in_array($this->status, [BookingStatus::Cancelled, BookingStatus::CheckedOut], true)) {
            throw new InvalidArgumentException('برای رزرو لغوشده یا چک‌اوت‌شده نمی‌توان از کیف پول پرداخت کرد.');
        }

        return DB::transaction(function () use ($amount) {
            $locked = static::query()->lockForUpdate()->with(['charges', 'payments'])->findOrFail($this->getKey());

            if ($amount <= 0 || $amount > $locked->walletPayableAmount()) {
                throw new InvalidArgumentException('مبلغ باید بین ۱ و مانده‌ی قابل پرداخت صورت‌حساب باشد.');
            }

            $transaction = $locked->guest->debitWallet($amount, "پرداخت صورت‌حساب رزرو {$locked->reference_code}", $locked);

            $locked->update(['wallet_paid_amount' => (int) $locked->wallet_paid_amount + $amount]);
            $this->setAttribute('wallet_paid_amount', $locked->wallet_paid_amount);
            $this->syncOriginalAttribute('wallet_paid_amount');

            return $transaction;
        });
    }

    /**
     * The share of the stay the guest pays if the booking ends now: from the
     * terms kept on the booking - the step for how many days are left before
     * check-in, or the "late" percentage after the last step. A no-show has
     * its own percentage. No terms means no penalty.
     */
    public function cancellationPenaltyPercent(bool $noShow = false): int
    {
        $terms = $this->cancellation_terms;

        if (blank($terms)) {
            return 0;
        }

        if ($noShow) {
            return (int) ($terms['no_show_percent'] ?? 0);
        }

        $daysLeft = (int) round(now()->startOfDay()->diffInDays($this->check_in_date->copy()->startOfDay(), false));
        $steps = collect($terms['rules'] ?? [])->sortByDesc('days');

        foreach ($steps as $step) {
            if ($daysLeft >= (int) $step['days']) {
                return (int) $step['percent'];
            }
        }

        return (int) ($terms['late_percent'] ?? 0);
    }

    public function cancellationFeeFor(bool $noShow = false): int
    {
        return (int) round($this->totalAmount() * $this->cancellationPenaltyPercent($noShow) / 100);
    }

    /**
     * Cancels a booking that has not started (or marks a no-show), charging
     * the fee its terms ask for unless it is waived. The wallet money that
     * is not needed for the fee goes back to the guest's wallet; cash that
     * was paid is handed back with a refund line (see refundableAmount()).
     *
     * @throws InvalidArgumentException
     */
    public function cancel(?string $reason = null, bool $noShow = false, bool $waiveFee = false): void
    {
        if (! in_array($this->status, [BookingStatus::Pending, BookingStatus::Confirmed], true)) {
            throw new InvalidArgumentException('فقط رزرو «در انتظار تأیید» یا «قطعی» را می‌توان لغو کرد.');
        }

        $this->update([
            'status' => BookingStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_kind' => $noShow ? self::CANCELLED_NO_SHOW : self::CANCELLED_BY_GUEST,
            'cancellation_reason' => $reason,
            'cancellation_fee' => $waiveFee ? 0 : $this->cancellationFeeFor($noShow),
        ]);
    }

    public function cancellationKindLabel(): ?string
    {
        return match ($this->cancellation_kind) {
            self::CANCELLED_NO_SHOW => 'عدم حضور',
            self::CANCELLED_BY_GUEST => 'لغو',
            default => null,
        };
    }

    /**
     * The cancellation fee is taken from the money received at the desk
     * first, then from what the wallet paid; whatever wallet money is left
     * over goes back into the wallet.
     */
    private function returnWalletMoneyAfterCancellation(): void
    {
        $walletPaid = (int) $this->wallet_paid_amount;

        if ($walletPaid <= 0) {
            return;
        }

        $this->load('payments');

        $feeLeftAfterReceived = max(0, (int) $this->cancellation_fee - $this->receivedAmount());
        $back = $walletPaid - min($walletPaid, $feeLeftAfterReceived);

        if ($back <= 0) {
            return;
        }

        DB::transaction(function () use ($back) {
            $this->guest->creditWallet($back, "بازگشت پرداخت رزرو لغوشده {$this->reference_code}", $this);
            $this->updateQuietly(['wallet_paid_amount' => (int) $this->wallet_paid_amount - $back]);
        });
    }

    /**
     * Fast check-in: mark the guest as arrived and free the room's
     * "reserved" state into "occupied".
     */
    public function checkIn(): void
    {
        $this->update([
            'status' => BookingStatus::CheckedIn,
            'actual_check_in_at' => now(),
        ]);

        $this->room->update(['status' => RoomStatus::Occupied]);
    }

    /**
     * Fast check-out: settle the folio, free the room into "needs
     * cleaning", and spawn a housekeeping task - reusing the same
     * checklist-template mechanism as the recurring checklist engine, if
     * the room's department has one defined (e.g. "چک‌لیست نظافت اتاق").
     */
    public function checkOut(): Task
    {
        $this->update([
            'status' => BookingStatus::CheckedOut,
            'actual_check_out_at' => now(),
        ]);

        $this->room->update(['status' => RoomStatus::NeedsCleaning]);

        $task = Task::create([
            'title' => "نظافت اتاق {$this->room->name}",
            'task_type' => TaskType::FromBooking,
            'source_id' => $this->id,
            'source_type' => self::class,
            'department_id' => $this->room->department_id,
            'priority' => TaskPriority::UrgentImportant,
            'status' => TaskStatus::Todo,
        ]);

        $template = TaskChecklistTemplate::query()
            ->where('department_id', $this->room->department_id)
            ->where('is_active', true)
            ->with('items')
            ->first();

        foreach ($template?->items ?? [] as $item) {
            $task->checklistItems()->create([
                'checklist_template_item_id' => $item->id,
                'label' => $item->label,
            ]);
        }

        $this->creditCashback();
        $this->calculateCommission();
        $this->requestPostCheckoutFeedback();

        return $task;
    }

    /**
     * OTA commission owed on this booking, computed from the sales
     * channel's commission_rate against the final folio total. Direct
     * bookings (no channel, or a "direct" channel with 0% rate) owe
     * nothing.
     */
    private function calculateCommission(): void
    {
        if (! $this->channel) {
            return;
        }

        $this->update([
            'commission_amount' => (int) round($this->totalAmount() * $this->channel->commission_rate / 100),
        ]);
    }

    /**
     * Loyalty cashback credited to the guest's wallet on checkout
     * (doc2 5.3 "کیف پول و امتیاز خرید"): the percentage depends on the
     * guest's loyalty tier and the optional validity period, both set in
     * WalletSetting.
     */
    private function creditCashback(): void
    {
        $settings = WalletSetting::current();
        $percent = $settings->cashbackPercentFor($this->guest->loyaltyTier());

        // Money that came out of the wallet earns no cashback, otherwise
        // the same toman would be re-credited again and again.
        $earningAmount = max(0, $this->totalAmount() - (int) $this->wallet_paid_amount);
        $amount = (int) round($earningAmount * $percent / 100);

        if ($amount > 0) {
            $expiresAt = $settings->cashback_expiry_days ? now()->addDays($settings->cashback_expiry_days) : null;

            $this->guest->creditWallet($amount, "کش‌بک رزرو {$this->reference_code}", $this, $expiresAt);
        }
    }

    /**
     * Creates the CRM record for a post-checkout survey (doc2 5.4). This
     * only marks a survey as *requested* - actually texting the guest a
     * survey link needs an SMS gateway integration, which is out of scope
     * here; a later phase (or wiring GuestFeedback::requested_at into an
     * n8n/SMS workflow) is expected to consume these records.
     */
    private function requestPostCheckoutFeedback(): GuestFeedback
    {
        return $this->guest->feedbacks()->create([
            'booking_id' => $this->id,
            'requested_at' => now(),
        ]);
    }
}
