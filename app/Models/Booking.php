<?php

namespace App\Models;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Exceptions\RoomNotAvailableException;
use App\Models\Concerns\HasTags;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Booking extends Model
{
    use HasFactory, HasTags;

    protected $fillable = [
        'reference_code', 'room_id', 'guest_id', 'check_in_date', 'check_out_date',
        'actual_check_in_at', 'actual_check_out_at', 'adults_count', 'children_count',
        'status', 'source', 'sales_channel_id', 'external_api_client_id', 'commission_amount', 'notes', 'created_by',
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
            'commission_amount' => 'decimal:0',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $booking) {
            $booking->reference_code ??= static::generateReferenceCode();

            // Best-effort guard for direct ::create() calls (tinker, relation
            // managers, seeders). The authoritative, concurrency-safe check
            // is CreateBooking::handleRecordCreation(), which wraps this in
            // a DB transaction with a row lock on the room.
            static::assertRoomAvailable(
                Room::findOrFail($booking->room_id),
                $booking->check_in_date instanceof Carbon ? $booking->check_in_date : Carbon::parse($booking->check_in_date),
                $booking->check_out_date instanceof Carbon ? $booking->check_out_date : Carbon::parse($booking->check_out_date),
            );
        });

        static::updating(function (self $booking) {
            // A booking being cancelled frees the room, so it can never
            // conflict with anything - skip the check entirely.
            if ($booking->status === BookingStatus::Cancelled) {
                return;
            }

            // Only worth checking when something that affects room
            // availability actually changed (dates, room, or a status
            // change that re-activates a previously cancelled booking).
            if (! $booking->isDirty(['room_id', 'check_in_date', 'check_out_date', 'status'])) {
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
        });

        static::created(function (self $booking) {
            $totalForStay = $booking->room->roomType->effectivePriceForRange(
                $booking->check_in_date,
                $booking->check_out_date,
            );

            $booking->charges()->create([
                'title' => "اقامت {$booking->nights()} شب",
                'quantity' => 1,
                'unit_amount' => $totalForStay,
            ]);
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
     * Loyalty cashback: a flat percentage of the folio total credited to
     * the guest's wallet on checkout (doc2 5.3 "کیف پول و امتیاز خرید").
     */
    public const CASHBACK_RATE = 0.02;

    private function creditCashback(): void
    {
        $amount = (int) round($this->totalAmount() * self::CASHBACK_RATE);

        if ($amount > 0) {
            $this->guest->creditWallet($amount, "کش‌بک رزرو {$this->reference_code}", $this);
        }
    }

    /**
     * Creates the CRM record for a post-checkout survey (doc2 5.4). This
     * only marks a survey as *requested* - actually texting the guest a
     * survey link needs an SMS gateway integration, which is out of scope
     * here; a later phase (or wiring GuestFeedback::requested_at into an
     * n8n/SMS workflow) is expected to consume these records.
     */
    private function requestPostCheckoutFeedback(): GuestFeedback
    {
        return $this->guest->feedbacks()->create([
            'booking_id' => $this->id,
            'requested_at' => now(),
        ]);
    }
}
