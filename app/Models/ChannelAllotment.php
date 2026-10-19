<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How many units of a room type one sales channel may hold on the same night.
 * The limit is enforced when a booking is made (Booking::assertChannelAllotment);
 * a room type with no row for a channel is not limited for that channel, and a
 * row with 0 closes the channel for that room type.
 */
class ChannelAllotment extends Model
{
    protected $fillable = ['room_type_id', 'sales_channel_id', 'allotment_count'];

    protected function casts(): array
    {
        return [
            'allotment_count' => 'integer',
        ];
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(SalesChannel::class, 'sales_channel_id');
    }

    /**
     * The most units of $roomTypeId that $channelId already holds on any single
     * night of [$checkIn, $checkOut). Cancelled bookings hold nothing.
     */
    public static function peakUsage(int $channelId, int $roomTypeId, CarbonInterface $checkIn, CarbonInterface $checkOut, ?int $excludeBookingId = null): int
    {
        $bookings = Booking::query()
            ->where('sales_channel_id', $channelId)
            ->where('status', '!=', BookingStatus::Cancelled)
            ->whereHas('room', fn ($query) => $query->where('room_type_id', $roomTypeId))
            ->when($excludeBookingId, fn ($query) => $query->whereKeyNot($excludeBookingId))
            ->where('check_in_date', '<', $checkOut->toDateString())
            ->where('check_out_date', '>', $checkIn->toDateString())
            ->get(['check_in_date', 'check_out_date']);

        $peak = 0;

        for ($night = $checkIn->copy()->startOfDay(); $night->lt($checkOut->copy()->startOfDay()); $night->addDay()) {
            $peak = max($peak, $bookings->filter(
                fn (Booking $booking) => $booking->check_in_date->lte($night) && $booking->check_out_date->gt($night),
            )->count());
        }

        return $peak;
    }

    /**
     * Whether the channel still has a free unit of the room type for every
     * night of the stay (always true when the channel has no allotment row).
     */
    public static function hasRoomFor(int $channelId, int $roomTypeId, CarbonInterface $checkIn, CarbonInterface $checkOut, ?int $excludeBookingId = null): bool
    {
        $allotment = static::query()->where('sales_channel_id', $channelId)->where('room_type_id', $roomTypeId)->first();

        return ! $allotment || static::peakUsage($channelId, $roomTypeId, $checkIn, $checkOut, $excludeBookingId) < $allotment->allotment_count;
    }
}
