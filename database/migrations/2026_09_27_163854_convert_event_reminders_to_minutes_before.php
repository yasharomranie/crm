<?php

use App\Models\EventReminder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * days_before + a fixed remind_time could only express whole-day
     * offsets ("2 days before, at 09:00") - there was no way to say "1
     * hour before" or "24 hours before" the event's own start time.
     * minutes_before is a single relative offset instead, which covers
     * both and everything in between.
     */
    public function up(): void
    {
        Schema::table('event_reminders', function (Blueprint $table) {
            $table->unsignedInteger('minutes_before')->default(0)->after('event_id');
        });

        EventReminder::query()->with('event')->each(function (EventReminder $reminder) {
            $oldRemindAt = $reminder->event->starts_at->copy()
                ->subDays((int) $reminder->getRawOriginal('days_before'))
                ->setTimeFromTimeString((string) $reminder->getRawOriginal('remind_time'));

            $minutesBefore = intdiv($reminder->event->starts_at->getTimestamp() - $oldRemindAt->getTimestamp(), 60);

            $reminder->forceFill(['minutes_before' => max(0, $minutesBefore)])->saveQuietly();
        });

        Schema::table('event_reminders', function (Blueprint $table) {
            $table->dropColumn(['days_before', 'remind_time']);
        });
    }

    public function down(): void
    {
        Schema::table('event_reminders', function (Blueprint $table) {
            $table->unsignedSmallInteger('days_before')->default(0)->after('event_id');
            $table->time('remind_time')->default('09:00:00')->after('days_before');
        });

        EventReminder::query()->with('event')->each(function (EventReminder $reminder) {
            $remindAt = $reminder->event->starts_at->copy()->subMinutes($reminder->minutes_before);

            $reminder->forceFill([
                'days_before' => $reminder->event->starts_at->copy()->startOfDay()->diffInDays($remindAt->copy()->startOfDay(), true),
                'remind_time' => $remindAt->format('H:i:s'),
            ])->saveQuietly();
        });

        Schema::table('event_reminders', function (Blueprint $table) {
            $table->dropColumn('minutes_before');
        });
    }
};
