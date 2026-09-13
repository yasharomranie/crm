<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasFactory;

    protected $fillable = ['room_type_id', 'department_id', 'name', 'status', 'is_active'];

    protected function casts(): array
    {
        return [
            'status' => RoomStatus::class,
            'is_active' => 'boolean',
        ];
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * The booking currently occupying this room (checked in, not yet
     * checked out) - used by the room rack board.
     */
    public function currentBooking(): ?Booking
    {
        return $this->bookings()
            ->where('status', BookingStatus::CheckedIn)
            ->latest('actual_check_in_at')
            ->first();
    }

    /**
     * The next confirmed/pending booking due to arrive, if any - shown on
     * the rack board for rooms that are currently free.
     */
    public function upcomingBooking(): ?Booking
    {
        return $this->bookings()
            ->whereIn('status', [BookingStatus::Pending, BookingStatus::Confirmed])
            ->where('check_in_date', '>=', now()->toDateString())
            ->orderBy('check_in_date')
            ->first();
    }
}
