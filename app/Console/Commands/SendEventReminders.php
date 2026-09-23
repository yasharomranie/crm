<?php

namespace App\Console\Commands;

use App\Models\EventReminder;
use App\Notifications\EventReminderNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

#[Signature('events:send-reminders')]
#[Description('Send every event reminder whose fire time has arrived and hasn\'t been sent yet')]
class SendEventReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = Carbon::now();

        // duePending() only filters on cheap, indexable columns (sent_at,
        // the event's own starts_at) - the exact "is it time yet" check
        // needs days_before + remind_time combined, which isn't something
        // a portable query can express, so it's done in PHP below instead.
        $reminders = EventReminder::query()
            ->duePending($now)
            ->with('event.participants', 'event.creator')
            ->get()
            ->filter(fn (EventReminder $reminder) => $reminder->remindAt()->lessThanOrEqualTo($now));

        $sent = 0;

        foreach ($reminders as $reminder) {
            $recipients = $reminder->event->participants->isNotEmpty()
                ? $reminder->event->participants
                : collect([$reminder->event->creator])->filter();

            if ($recipients->isEmpty()) {
                $reminder->update(['sent_at' => $now]);

                continue;
            }

            Notification::send($recipients, new EventReminderNotification($reminder));

            // Marked sent as soon as it's handed off to the queue, not
            // after delivery succeeds - this command's job is "did we try
            // to send this reminder once", the same idempotency guarantee
            // as GenerateRecurringChecklistTasks has for its own tasks.
            // A per-channel delivery failure (e.g. sms.ir down) is the
            // channel's own concern (see SmsGateway), not a reason to
            // resend the whole reminder later.
            $reminder->update(['sent_at' => $now]);
            $sent++;
        }

        $this->info("Done. {$sent} reminder(s) sent out of {$reminders->count()} due.");

        return self::SUCCESS;
    }
}
