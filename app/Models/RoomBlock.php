<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A room taken out of sale for a date range (repairs, painting, owner use).
 * The nights are [starts_on, ends_on): the end day itself is free again,
 * like a booking's check-out day.
 */
class RoomBlock extends Model
{
    use HasFactory;

    protected $fillable = ['room_id', 'starts_on', 'ends_on', 'reason', 'created_by'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Blocks overlapping the nights [$from, $to).
     *
     * @param  Builder<RoomBlock>  $query
     * @return Builder<RoomBlock>
     */
    public function scopeOverlapping(Builder $query, CarbonInterface $from, CarbonInterface $to): Builder
    {
        return $query
            ->where('starts_on', '<', $to->toDateString())
            ->where('ends_on', '>', $from->toDateString());
    }

    /**
     * Live bookings of the room that already sleep a guest on a blocked night -
     * a block cannot be placed over them without moving them first.
     */
    public static function conflictingBookingsCount(int $roomId, CarbonInterface $from, CarbonInterface $to): int
    {
        return Booking::query()
            ->where('room_id', $roomId)
            ->where('status', '!=', BookingStatus::Cancelled)
            ->where('check_in_date', '<', $to->toDateString())
            ->where('check_out_date', '>', $from->toDateString())
            ->count();
    }
}
