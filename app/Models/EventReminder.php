<?php

namespace App\Models;

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
        'event_id', 'days_before', 'remind_time',
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
     * The moment this reminder should fire: the event's own date, moved
     * back by days_before, with its time-of-day replaced by remind_time
     * (an independent, admin-chosen "alarm time" - e.g. an event on the
     * 10th with days_before=1 and remind_time=09:00 fires on the 9th at
     * 09:00, regardless of what time the event itself starts).
     */
    public function remindAt(): Carbon
    {
        return $this->event->starts_at->copy()
            ->subDays($this->days_before)
            ->setTimeFromTimeString((string) $this->remind_time);
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
