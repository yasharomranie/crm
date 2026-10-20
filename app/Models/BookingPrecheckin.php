<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * What the guest tells the hotel before arriving (online pre-registration):
 * national code, expected arrival time, special requests and the people
 * travelling with them. The token in the link is the only key, so it is long
 * and random; the form works while the stay can still happen.
 */
class BookingPrecheckin extends Model
{
    public const MAX_COMPANIONS = 10;

    protected $fillable = ['booking_id', 'token', 'national_code', 'arrival_time', 'requests', 'companions', 'completed_at'];

    protected function casts(): array
    {
        return [
            'companions' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $precheckin) {
            $precheckin->token ??= Str::random(40);
        });
    }

    /**
     * The booking's pre-registration, made on first use so its link stays the same.
     */
    public static function forBooking(Booking $booking): self
    {
        return static::query()->firstOrCreate(['booking_id' => $booking->id]);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function url(): string
    {
        return route('booking-precheckin.show', $this->token);
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * Can the guest still fill (or change) the form?
     */
    public function isOpen(): bool
    {
        return in_array($this->booking->status, [BookingStatus::Pending, BookingStatus::Confirmed], true);
    }
}
