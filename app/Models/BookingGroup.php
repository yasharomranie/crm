<?php

namespace App\Models;

use App\Enums\BookingPaymentKind;
use App\Enums\BookingPaymentMethod;
use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * A group of rooms booked together (a family, a trip): one responsible guest,
 * one code, one place to see what the whole group owes and to pay it. Every
 * room stays an ordinary booking, so availability, check-in, cancellation,
 * deposits and everything else keep working per room; the group only adds
 * totals, one payment that is shared across the rooms, and one invoice.
 */
class BookingGroup extends Model
{
    protected $fillable = ['code', 'name', 'guest_id', 'notes', 'created_by'];

    protected static function booted(): void
    {
        static::creating(function (self $group) {
            $group->code ??= 'GRP-'.str_pad((string) (static::max('id') + 1), 6, '0', STR_PAD_LEFT);
        });
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * The group's rooms in order of arrival, with what is needed to price them.
     * Uses the already loaded relation (the list page eager-loads it) so a
     * table of groups does not query once per row.
     *
     * @return Collection<int, Booking>
     */
    private function loadedBookings(): Collection
    {
        $bookings = $this->relationLoaded('bookings') ? $this->bookings : $this->bookings()->get();

        return $bookings->loadMissing(['charges', 'payments', 'room'])
            ->sortBy(fn (Booking $booking) => sprintf('%s-%010d', $booking->check_in_date->toDateString(), $booking->id))
            ->values();
    }

    /**
     * What the group owes: each room's folio, or only the fee of a cancelled room.
     */
    public function totalAmount(): int
    {
        return (int) $this->loadedBookings()->sum(fn (Booking $booking) => $booking->amountOwed());
    }

    public function paidAmount(): int
    {
        return (int) $this->loadedBookings()->sum(fn (Booking $booking) => $booking->paidAmount());
    }

    public function balanceDue(): int
    {
        return (int) $this->loadedBookings()->sum(fn (Booking $booking) => $booking->balanceDue());
    }

    /**
     * Takes one payment for the whole group and spreads it over the rooms in
     * order of arrival, each up to its own unpaid balance. Returns the amount
     * given to each booking (by booking id).
     *
     * @return array<int, int>
     *
     * @throws InvalidArgumentException
     */
    public function receivePayment(int $amount, BookingPaymentMethod $method, ?string $reference = null, ?string $note = null, ?int $userId = null): array
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('مبلغ باید بیشتر از صفر باشد.');
        }

        return DB::transaction(function () use ($amount, $method, $reference, $note, $userId) {
            $bookings = $this->loadedBookings();
            $balance = (int) $bookings->sum(fn (Booking $booking) => $booking->balanceDue());

            if ($amount > $balance) {
                throw new InvalidArgumentException('مبلغ از مانده‌ی کل گروه ('.number_format($balance).' تومان) بیشتر است.');
            }

            $remaining = $amount;
            $allocation = [];

            foreach ($bookings as $booking) {
                $share = min($remaining, $booking->balanceDue());

                if ($share <= 0) {
                    continue;
                }

                $booking->recordPayment($share, $method, BookingPaymentKind::Payment, $reference, trim("پرداخت گروهی {$this->code} ".$note), $userId);
                $allocation[$booking->id] = $share;
                $remaining -= $share;

                if ($remaining === 0) {
                    break;
                }
            }

            return $allocation;
        });
    }

    /**
     * Cancels every room that has not arrived yet (pending or confirmed), each
     * by its own cancellation terms; rooms already in the house or finished are
     * left alone. Returns how many rooms were cancelled.
     */
    public function cancelAll(?string $reason = null, bool $waiveFee = false): int
    {
        return DB::transaction(function () use ($reason, $waiveFee) {
            $cancelled = 0;

            foreach ($this->bookings()->whereIn('status', [BookingStatus::Pending, BookingStatus::Confirmed])->get() as $booking) {
                $booking->cancel($reason, false, $waiveFee);
                $cancelled++;
            }

            return $cancelled;
        });
    }
}
