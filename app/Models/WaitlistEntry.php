<?php

namespace App\Models;

use Database\Factories\WaitlistEntryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A guest who wanted a stay when everything was full. When a room of the
 * wanted type becomes free for the wanted nights (a cancellation, a no-show, a
 * released hold) the guest is texted once and the entry moves to «اطلاع داده شد»;
 * the front desk then books it for them.
 */
class WaitlistEntry extends Model
{
    /** @use HasFactory<WaitlistEntryFactory> */
    use HasFactory;

    public const STATUS_WAITING = 'waiting';

    public const STATUS_NOTIFIED = 'notified';

    public const STATUS_BOOKED = 'booked';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'guest_id', 'room_type_id', 'check_in_date', 'check_out_date', 'adults_count', 'children_count',
        'notes', 'status', 'notified_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'check_in_date' => 'date',
            'check_out_date' => 'date',
            'adults_count' => 'integer',
            'children_count' => 'integer',
            'notified_at' => 'datetime',
        ];
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    /**
     * Entries still waiting for a room whose arrival has not passed.
     *
     * @param  Builder<WaitlistEntry>  $query
     * @return Builder<WaitlistEntry>
     */
    public function scopeWaiting(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_WAITING)->whereDate('check_in_date', '>=', today()->toDateString());
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_WAITING => 'در انتظار اتاق',
            self::STATUS_NOTIFIED => 'اطلاع داده شد',
            self::STATUS_BOOKED => 'رزرو شد',
            self::STATUS_CLOSED => 'بسته شد',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? $this->status;
    }

    /**
     * Is a room of the wanted type free for all the wanted nights right now?
     */
    public function hasFreeRoom(): bool
    {
        return Room::query()
            ->freeBetween($this->check_in_date, $this->check_out_date)
            ->when($this->room_type_id, fn ($query, $typeId) => $query->where('room_type_id', $typeId))
            ->exists();
    }
}
