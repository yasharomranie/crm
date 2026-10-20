<?php

namespace App\Models;

use Database\Factories\LostItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something a guest left behind: what it is, where it was found, whose it
 * probably is (the stay it came from) and how it ended - the guest was told,
 * it was handed back, or it was put away unclaimed.
 */
class LostItem extends Model
{
    /** @use HasFactory<LostItemFactory> */
    use HasFactory;

    public const STATUS_FOUND = 'found';

    public const STATUS_CONTACTED = 'contacted';

    public const STATUS_RETURNED = 'returned';

    public const STATUS_DISPOSED = 'disposed';

    protected $fillable = [
        'booking_id', 'guest_id', 'room_id', 'description', 'location', 'found_on', 'status',
        'contacted_at', 'closed_at', 'closing_note', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'found_on' => 'date',
            'contacted_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_FOUND => 'پیدا شده',
            self::STATUS_CONTACTED => 'به مهمان خبر داده شد',
            self::STATUS_RETURNED => 'تحویل داده شد',
            self::STATUS_DISPOSED => 'بایگانی / دور ریخته شد',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? $this->status;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_FOUND, self::STATUS_CONTACTED], true);
    }
}
