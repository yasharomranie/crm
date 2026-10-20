<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A room taken out of sale (repairs, painting, owner use). The nights are
 * [starts_on, ends_on): the end day itself is free again, like a booking's
 * check-out day. A block without an end stays until someone ends it.
 *
 * This is the single place that says «this unit is out of service»: setting a
 * room's own status to «خارج از سرویس» opens such a block (source «status»)
 * and putting it back on sale closes it (see Room::booted()).
 */
class RoomBlock extends Model
{
    use HasFactory;

    public const SOURCE_STATUS = 'status';

    protected $fillable = ['room_id', 'starts_on', 'ends_on', 'reason', 'source', 'created_by'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(fn (self $block) => $block->restoreRoomStatus());
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function isOpenEnded(): bool
    {
        return $this->ends_on === null;
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
            ->whereDate('starts_on', '<', $to->toDateString())
            ->where(fn (Builder $ends) => $ends->whereNull('ends_on')->orWhereDate('ends_on', '>', $from->toDateString()));
    }

    /**
     * Live bookings of the room that already sleep a guest on a blocked night -
     * a block cannot be placed over them without moving them first. A null end
     * means «until further notice».
     */
    public static function conflictingBookingsCount(int $roomId, CarbonInterface $from, ?CarbonInterface $to = null): int
    {
        return Booking::query()
            ->where('room_id', $roomId)
            ->where('status', '!=', BookingStatus::Cancelled)
            ->when($to, fn (Builder $bookings) => $bookings->whereDate('check_in_date', '<', $to->toDateString()))
            ->whereDate('check_out_date', '>', $from->toDateString())
            ->count();
    }

    /**
     * Back in service from today: a block that has not started yet disappears,
     * a running one ends today (today's night is sold again).
     */
    public function endNow(): void
    {
        if ($this->starts_on->gte(today())) {
            $this->delete();

            return;
        }

        $this->update(['ends_on' => today()]);
        $this->restoreRoomStatus();
    }

    /**
     * A block born from the room's status releases that status when it ends.
     */
    public function restoreRoomStatus(): void
    {
        if ($this->source !== self::SOURCE_STATUS) {
            return;
        }

        $room = $this->room()->first();

        if ($room?->status === RoomStatus::OutOfService) {
            $room->update(['status' => RoomStatus::Available]);
        }
    }
}
