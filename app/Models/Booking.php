<?php

namespace App\Models;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Concerns\HasTags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Booking extends Model
{
    use HasTags;

    protected $fillable = [
        'reference_code', 'room_id', 'guest_id', 'check_in_date', 'check_out_date',
        'actual_check_in_at', 'actual_check_out_at', 'adults_count', 'children_count',
        'status', 'source', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'check_in_date' => 'date',
            'check_out_date' => 'date',
            'actual_check_in_at' => 'datetime',
            'actual_check_out_at' => 'datetime',
            'status' => BookingStatus::class,
            'source' => BookingSource::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $booking) {
            $booking->reference_code ??= static::generateReferenceCode();
        });

        static::created(function (self $booking) {
            $booking->charges()->create([
                'title' => "اقامت {$booking->nights()} شب",
                'quantity' => $booking->nights(),
                'unit_amount' => $booking->room->roomType->base_price,
            ]);
        });
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function charges(): HasMany
    {
        return $this->hasMany(BookingCharge::class);
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

        return $task;
    }
}
