<?php

namespace App\Models;

use App\Domain\Hospitality\StayPricing;
use App\Enums\BookingPaymentKind;
use App\Enums\BookingPaymentMethod;
use App\Enums\BookingPaymentStatus;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Exceptions\ChannelAllotmentExceededException;
use App\Exceptions\InsufficientWalletBalanceException;
use App\Exceptions\MinimumStayException;
use App\Exceptions\RoomNotAvailableException;
use App\Jobs\SendBookingMessageJob;
use App\Models\Concerns\HasTags;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'confirmation_sent_at', 'arrival_reminder_sent_at', 'feedback_sent_at', 'promo_code_id', 'hold_expires_at',
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
            'confirmation_sent_at' => 'datetime',
            'arrival_reminder_sent_at' => 'datetime',
            'feedback_sent_at' => 'datetime',
            'hold_expires_at' => 'datetime',
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

            if ($booking->status !== BookingStatus::Cancelled) {
                $room = Room::findOrFail($booking->room_id);
                $checkIn = $booking->check_in_date instanceof Carbon ? $booking->check_in_date : Carbon::parse($booking->check_in_date);
                $checkOut = $booking->check_out_date instanceof Carbon ? $booking->check_out_date : Carbon::parse($booking->check_out_date);

                static::assertChannelAllotment($room, $booking->sales_channel_id, $checkIn, $checkOut);
                static::assertMinimumStay($room, $checkIn, $checkOut);

                if ($booking->promo_code_id) {
                    BookingPromoCode::query()->lockForUpdate()->findOrFail($booking->promo_code_id)
                        ->assertUsable(max(1, (int) $checkIn->copy()->startOfDay()->diffInDays($checkOut->copy()->startOfDay())));
                }
            }
        });

        static::updating(function (self $booking) {
            // A hold only makes sense while the booking waits for money.
            if ($booking->isDirty('status') && $booking->status !== BookingStatus::Pending) {
                $booking->hold_expires_at = null;
            }

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
            if (! $booking->isDirty(['room_id', 'check_in_date', 'check_out_date', 'status', 'sales_channel_id'])) {
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

            // A status change such as check-in must never be blocked by an
            // allotment lowered after the booking was made; only moving the
            // booking (room, dates, channel) or reviving a cancelled one
            // counts against the allotment again.
            if ($booking->isDirty(['room_id', 'check_in_date', 'check_out_date'])
                || $booking->getOriginal('status') === BookingStatus::Cancelled) {
                static::assertMinimumStay(
                    Room::findOrFail($booking->room_id),
                    $booking->check_in_date instanceof Carbon ? $booking->check_in_date : Carbon::parse($booking->check_in_date),
                    $booking->check_out_date instanceof Carbon ? $booking->check_out_date : Carbon::parse($booking->check_out_date),
                );
            }

            if ($booking->isDirty(['room_id', 'check_in_date', 'check_out_date', 'sales_channel_id'])
                || $booking->getOriginal('status') === BookingStatus::Cancelled) {
                static::assertChannelAllotment(
                    Room::findOrFail($booking->room_id),
                    $booking->sales_channel_id,
                    $booking->check_in_date instanceof Carbon ? $booking->check_in_date : Carbon::parse($booking->check_in_date),
                    $booking->check_out_date instanceof Carbon ? $booking->check_out_date : Carbon::parse($booking->check_out_date),
                    $booking->id,
                );
            }
        });

        static::updated(function (self $booking) {
            if ($booking->wasChanged('status') && $booking->status === BookingStatus::Cancelled) {
                $booking->returnWalletMoneyAfterCancellation();
                $booking->refundDamageDepositAfterCancellation();
            }
        });

        static::created(function (self $booking) {
            $promo = $booking->promo_code_id ? BookingPromoCode::query()->find($booking->promo_code_id) : null;
            $adults = max(1, (int) $booking->adults_count);
            $children = (int) $booking->children_count;

            $pricing = app(StayPricing::class);
            $quote = $pricing->quote($booking->room->roomType, $booking->check_in_date, $booking->check_out_date, $adults, $children, $promo);

            foreach ($pricing->folioLines($quote, $adults, $children) as $line) {
                $booking->charges()->create([
                    'title' => $line['title'],
                    'quantity' => 1,
                    'unit_amount' => $line['amount'],
                ]);
            }

            $promo?->increment('used_count');
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

    /**
     * The stay must be at least as long as the room type (and any price rule
     * on those nights) requires.
     */
    public static function assertMinimumStay(Room $room, Carbon $checkIn, Carbon $checkOut): void
    {
        $roomType = $room->roomType;
        $nights = max(1, (int) $checkIn->copy()->startOfDay()->diffInDays($checkOut->copy()->startOfDay()));
        $minimum = $roomType->minimumNightsFor($checkIn, $checkOut);

        if ($nights < $minimum) {
            throw MinimumStayException::forStay($roomType->name, $minimum);
        }
    }

    /**
     * A channel may sell only as many units of a room type per night as its
     * allotment says (ChannelAllotment). The allotment row is locked while
     * checking, so two bookings made at the same moment through the same
     * channel cannot both take the last unit.
     */
    public static function assertChannelAllotment(Room $room, ?int $channelId, Carbon $checkIn, Carbon $checkOut, ?int $excludeBookingId = null): void
    {
        if (! $channelId) {
            return;
        }

        $allotment = ChannelAllotment::query()
            ->where('sales_channel_id', $channelId)
            ->where('room_type_id', $room->room_type_id)
            ->lockForUpdate()
            ->first();

        if (! $allotment) {
            return;
        }

        if (ChannelAllotment::peakUsage($channelId, $room->room_type_id, $checkIn, $checkOut, $excludeBookingId) >= $allotment->allotment_count) {
            throw ChannelAllotmentExceededException::forChannel(
                (string) SalesChannel::find($channelId)?->name,
                (string) $room->roomType?->name,
                $allotment->allotment_count,
            );
        }
    }

    public static function generateReferenceCode(): string
    {
        $sequence = str_pad((string) (static::max('id') + 1), 6, '0', STR_PAD_LEFT);

        return "GNJ-BKG-{$sequence}";
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(BookingPromoCode::class);
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

    public function paymentLinks(): HasMany
    {
        return $this->hasMany(BookingPaymentLink::class);
    }

    /**
     * Cancels every pending booking whose hold has run out and that has not
     * received any money; returns how many were released. A booking that got
     * a payment (or wallet money) keeps its place - staff decide about it.
     */
    public static function releaseExpiredHolds(): int
    {
        $released = 0;

        static::query()
            ->with(['payments'])
            ->where('status', BookingStatus::Pending)
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '<=', now())
            ->each(function (self $booking) use (&$released) {
                if ($booking->paidAmount() > 0) {
                    $booking->update(['hold_expires_at' => null]);

                    return;
                }

                $booking->cancel('مهلت نگه‌داشتن رزرو بدون پرداخت تمام شد', false, true);
                $released++;
            });

        return $released;
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

    /**
     * The statuses in which a stay can still be changed (not cancelled or over).
     *
     * @return list<BookingStatus>
     */
    private static function changeableStatuses(): array
    {
        return [BookingStatus::Pending, BookingStatus::Confirmed, BookingStatus::CheckedIn];
    }

    /**
     * What staying until $newCheckOut instead would add to the folio: the extra
     * nights at their nightly rates plus the extra guests' fees for them. The
     * long-stay discount and promo codes are not re-applied to an extension.
     */
    public function extensionPrice(CarbonInterface $newCheckOut): int
    {
        $quote = app(StayPricing::class)->quote(
            $this->room->roomType,
            $this->check_out_date,
            $newCheckOut,
            max(1, (int) $this->adults_count),
            (int) $this->children_count,
        );

        return $quote['room_total'] + $quote['extra_guests_total'];
    }

    /**
     * Extends the stay to a later check-out date, adding the extra nights to
     * the folio. The room must be free for them and any channel allotment must
     * still hold; both are enforced by the same checks as a new booking.
     *
     * @throws InvalidArgumentException
     * @throws RoomNotAvailableException
     */
    public function extendStay(CarbonInterface $newCheckOut, ?int $userId = null): void
    {
        if (! in_array($this->status, self::changeableStatuses(), true)) {
            throw new InvalidArgumentException('فقط اقامت رزرو «در انتظار»، «قطعی» یا «مقیم» را می‌توان تمدید کرد.');
        }

        if (! $newCheckOut->copy()->startOfDay()->gt($this->check_out_date)) {
            throw new InvalidArgumentException('تاریخ خروج تازه باید بعد از تاریخ خروج فعلی ('.$this->check_out_date->toDateString().') باشد.');
        }

        try {
            $this->extendStayInTransaction($newCheckOut, $userId);
        } catch (\Throwable $exception) {
            // A refused change must not leave the unsaved new date on this instance.
            $this->refresh();

            throw $exception;
        }
    }

    private function extendStayInTransaction(CarbonInterface $newCheckOut, ?int $userId): void
    {
        DB::transaction(function () use ($newCheckOut, $userId) {
            Room::query()->lockForUpdate()->findOrFail($this->room_id);

            $price = $this->extensionPrice($newCheckOut);
            $oldCheckOut = $this->check_out_date->copy();

            // The updating hook re-checks the room and the channel allotment for the new range.
            $this->update(['check_out_date' => $newCheckOut->copy()->startOfDay()]);

            $extraNights = (int) $oldCheckOut->diffInDays($this->check_out_date);

            $this->charges()->create([
                'title' => "تمدید اقامت {$extraNights} شب (تا ".$this->check_out_date->toJalali('Y/m/d').')',
                'quantity' => 1,
                'unit_amount' => $price,
            ]);

            activity('bookings')->performedOn($this)->causedBy($userId ? User::find($userId) : null)
                ->log("تمدید اقامت {$this->reference_code} از {$oldCheckOut->toDateString()} تا {$this->check_out_date->toDateString()}");
        });
    }

    /**
     * The folio difference of moving the stay to another room type: the new
     * type's room nights minus the old type's, for the whole stay (positive =
     * the guest pays more). Zero for a room of the same type.
     */
    public function roomChangeDifference(Room $newRoom): int
    {
        if ((int) $newRoom->room_type_id === (int) $this->room->room_type_id) {
            return 0;
        }

        $pricing = app(StayPricing::class);
        $old = $pricing->quote($this->room->roomType, $this->check_in_date, $this->check_out_date);
        $new = $pricing->quote($newRoom->roomType, $this->check_in_date, $this->check_out_date);

        return $new['room_total'] - $old['room_total'];
    }

    /**
     * Moves the whole booking to another room that is free for all its nights.
     * With $chargeDifference a different room type adds (or subtracts) the
     * price difference as its own folio line; otherwise the guest keeps the
     * agreed price (a free upgrade). A guest already in the house also flips
     * the two rooms' statuses (old: needs cleaning, new: occupied).
     *
     * @throws InvalidArgumentException
     * @throws RoomNotAvailableException
     */
    public function changeRoom(Room $newRoom, bool $chargeDifference = true, ?int $userId = null): void
    {
        if (! in_array($this->status, self::changeableStatuses(), true)) {
            throw new InvalidArgumentException('فقط اتاق رزرو «در انتظار»، «قطعی» یا «مقیم» را می‌توان عوض کرد.');
        }

        if ((int) $newRoom->getKey() === (int) $this->room_id) {
            throw new InvalidArgumentException('اتاق انتخاب‌شده همان اتاق فعلی است.');
        }

        try {
            $this->changeRoomInTransaction($newRoom, $chargeDifference, $userId);
        } catch (\Throwable $exception) {
            $this->refresh();

            throw $exception;
        }
    }

    private function changeRoomInTransaction(Room $newRoom, bool $chargeDifference, ?int $userId): void
    {
        DB::transaction(function () use ($newRoom, $chargeDifference, $userId) {
            $newRoom = Room::query()->lockForUpdate()->with('roomType')->findOrFail($newRoom->getKey());
            $oldRoom = $this->room;
            $difference = $chargeDifference ? $this->roomChangeDifference($newRoom) : 0;

            // The updating hook checks that the new room is free for the whole stay.
            $this->update(['room_id' => $newRoom->id]);
            $this->unsetRelation('room');

            if ($difference !== 0) {
                $this->charges()->create([
                    'title' => "تغییر اتاق از «{$oldRoom->name}» به «{$newRoom->name}»".($difference > 0 ? ' (مابه‌التفاوت)' : ' (کاهش نرخ)'),
                    'quantity' => 1,
                    'unit_amount' => $difference,
                ]);
            }

            if ($this->status === BookingStatus::CheckedIn) {
                $oldRoom->update(['status' => RoomStatus::NeedsCleaning]);
                $newRoom->update(['status' => RoomStatus::Occupied]);
            }

            activity('bookings')->performedOn($this)->causedBy($userId ? User::find($userId) : null)
                ->log("تغییر اتاق {$this->reference_code} از {$oldRoom->name} به {$newRoom->name}");
        });
    }

    public function damageDeposit(): HasOne
    {
        return $this->hasOne(DamageDeposit::class);
    }

    /**
     * Takes (more of) the guest's damage deposit. It never touches the folio
     * or the payment ledger: the deposit is held for the guest, not earned.
     *
     * @throws InvalidArgumentException
     */
    public function receiveDamageDeposit(int $amount, BookingPaymentMethod $method, ?int $userId = null): DamageDeposit
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('مبلغ ودیعه باید بیشتر از صفر باشد.');
        }

        if (! in_array($this->status, self::changeableStatuses(), true)) {
            throw new InvalidArgumentException('برای این رزرو نمی‌توان ودیعه گرفت.');
        }

        return DB::transaction(function () use ($amount, $method, $userId) {
            $deposit = DamageDeposit::query()->lockForUpdate()->firstOrCreate(['booking_id' => $this->id], ['created_by' => $userId]);

            if ($deposit->isSettled()) {
                throw new InvalidArgumentException('ودیعه‌ی این رزرو قبلاً تسویه شده است.');
            }

            $deposit->update([
                'held_amount' => $deposit->held_amount + $amount,
                'method' => $method,
                'received_at' => now(),
            ]);

            activity('bookings')->performedOn($this)->causedBy($userId ? User::find($userId) : null)
                ->log("دریافت ودیعه‌ی خسارت {$amount} تومان برای {$this->reference_code}");

            $this->unsetRelation('damageDeposit');

            return $deposit;
        });
    }

    /**
     * Ends the deposit once: $deduct toman are kept for damage, the rest is
     * given back to the guest.
     *
     * @throws InvalidArgumentException
     */
    public function settleDamageDeposit(int $deduct = 0, ?string $note = null, ?int $userId = null): DamageDeposit
    {
        return DB::transaction(function () use ($deduct, $note, $userId) {
            $deposit = DamageDeposit::query()->lockForUpdate()->where('booking_id', $this->id)->first();

            if (! $deposit || $deposit->held_amount <= 0) {
                throw new InvalidArgumentException('ودیعه‌ای برای این رزرو دریافت نشده است.');
            }

            if ($deposit->isSettled()) {
                throw new InvalidArgumentException('ودیعه‌ی این رزرو قبلاً تسویه شده است.');
            }

            if ($deduct < 0 || $deduct > $deposit->held_amount) {
                throw new InvalidArgumentException('مبلغ کسر باید بین ۰ و '.number_format($deposit->held_amount).' تومان باشد.');
            }

            $deposit->update([
                'deducted_amount' => $deduct,
                'refunded_amount' => $deposit->held_amount - $deduct,
                'settlement_note' => $note,
                'settled_at' => now(),
            ]);

            activity('bookings')->performedOn($this)->causedBy($userId ? User::find($userId) : null)
                ->log("تسویه‌ی ودیعه‌ی {$this->reference_code}: {$deduct} تومان کسر، ".($deposit->held_amount - $deduct).' تومان برگشت');

            $this->unsetRelation('damageDeposit');

            return $deposit;
        });
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
    /**
     * A cancelled stay has no damage: whatever deposit was taken is given back in full.
     */
    private function refundDamageDepositAfterCancellation(): void
    {
        $deposit = $this->damageDeposit()->first();

        if ($deposit && $deposit->held_amount > 0 && ! $deposit->isSettled()) {
            $this->settleDamageDeposit(0, 'رزرو لغو شد؛ ودیعه کامل برگشت داده می‌شود.');
        }
    }

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
     * Creates the CRM record for a post-checkout survey (doc2 5.4) and, when
     * the «پیام‌های رزرو» setting is on, texts the guest its link.
     */
    private function requestPostCheckoutFeedback(): GuestFeedback
    {
        $feedback = $this->guest->feedbacks()->create([
            'booking_id' => $this->id,
            'requested_at' => now(),
        ]);

        if (BookingMessageSetting::current()->feedback_enabled) {
            SendBookingMessageJob::dispatch($this->id, 'feedback', $feedback->id);
        }

        return $feedback;
    }
}
