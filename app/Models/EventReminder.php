<?php

namespace App\Models;

use App\Enums\EventReminderUnit;
use Carbon\Carbon;
use Database\Factories\EventReminderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventReminder extends Model
{
    /** @use HasFactory<EventReminderFactory> */
    use HasFactory;

    protected $fillable = [
        'event_id', 'minutes_before',
        'notify_push', 'notify_sms', 'notify_email', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'notify_push' => 'boolean',
            'notify_sms' => 'boolean',
            'notify_email' => 'boolean',
            'sent_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * The moment this reminder should fire: the event's own start time,
     * moved back by minutes_before - a plain relative offset (e.g. "1 week
     * before", "24 hours before", "1 hour before"), so it always tracks
     * the event's actual start rather than an independently chosen clock
     * time.
     */
    public function remindAt(): Carbon
    {
        return $this->event->starts_at->copy()->subMinutes($this->minutes_before);
    }

    /**
     * A human-readable "N ساعت قبل" / "N هفته قبل" label for the admin
     * table - see EventReminderUnit::decompose() for how minutes_before is
     * broken back down into the largest whole unit.
     */
    public function label(): string
    {
        $decomposed = EventReminderUnit::decompose($this->minutes_before);

        if ($decomposed['value'] === 0) {
            return 'همان لحظه‌ی شروع رویداد';
        }

        return "{$decomposed['value']} {$decomposed['unit']->getLabel()} قبل";
    }

    /**
     * Reminders whose fire time has arrived, haven't been sent yet, and
     * whose event hasn't already happened (a reminder skipped past due to
     * downtime shouldn't fire late for an event that's already over).
     */
    public function scopeDuePending(Builder $query, ?Carbon $now = null): Builder
    {
        $now ??= Carbon::now();

        return $query->whereNull('sent_at')
            ->whereHas('event', fn (Builder $q) => $q->where('starts_at', '>', $now));
    }
}
