<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventReminder;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\EventReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\TestCase;

class EventReminderSchedulingTest extends TestCase
{
    use RefreshDatabase;

    public function test_remind_at_subtracts_minutes_before_from_the_events_start_time(): void
    {
        $event = Event::factory()->create(['starts_at' => '2026-10-10 18:00:00']);
        $reminder = EventReminder::factory()->create([
            'event_id' => $event->id,
            'minutes_before' => 60 * 24, // 24 hours before
        ]);

        $this->assertSame('2026-10-09 18:00:00', $reminder->remindAt()->format('Y-m-d H:i:s'));
    }

    public function test_label_picks_the_largest_whole_unit_that_fits(): void
    {
        $event = Event::factory()->create();

        $week = EventReminder::factory()->create(['event_id' => $event->id, 'minutes_before' => 60 * 24 * 7]);
        $hours24 = EventReminder::factory()->create(['event_id' => $event->id, 'minutes_before' => 60 * 24]);
        $hour = EventReminder::factory()->create(['event_id' => $event->id, 'minutes_before' => 60]);
        $oddMinutes = EventReminder::factory()->create(['event_id' => $event->id, 'minutes_before' => 90]);

        $this->assertSame('1 هفته قبل', $week->label());
        $this->assertSame('1 روز قبل', $hours24->label());
        $this->assertSame('1 ساعت قبل', $hour->label());
        $this->assertSame('90 دقیقه قبل', $oddMinutes->label());
    }

    public function test_the_command_sends_a_due_reminder_to_every_participant_and_marks_it_sent(): void
    {
        Notification::fake();

        $event = Event::factory()->create(['starts_at' => now()->addDay()]);
        $participants = User::factory()->count(2)->create();
        $event->participants()->attach($participants);

        $reminder = EventReminder::factory()->create([
            'event_id' => $event->id,
            'minutes_before' => 60 * 24,
        ]);

        $this->artisan('events:send-reminders')->assertSuccessful();

        Notification::assertSentTo($participants, EventReminderNotification::class);
        $this->assertNotNull($reminder->fresh()->sent_at);
    }

    public function test_the_command_does_not_send_a_reminder_whose_alarm_time_has_not_arrived_yet(): void
    {
        Notification::fake();

        $event = Event::factory()->create(['starts_at' => now()->addDays(5)]);
        $reminder = EventReminder::factory()->create([
            'event_id' => $event->id,
            'minutes_before' => 60 * 24 * 4, // fires 1 day from now, not yet
        ]);

        $this->artisan('events:send-reminders')->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertNull($reminder->fresh()->sent_at);
    }

    public function test_the_command_never_sends_the_same_reminder_twice(): void
    {
        Notification::fake();

        $event = Event::factory()->create(['starts_at' => now()->addDay()]);
        $event->participants()->attach(User::factory()->create());

        EventReminder::factory()->create([
            'event_id' => $event->id,
            'minutes_before' => 60 * 24,
        ]);

        $this->artisan('events:send-reminders');
        $this->artisan('events:send-reminders');

        Notification::assertSentToTimes($event->participants->first(), EventReminderNotification::class, 1);
    }

    public function test_a_reminder_with_only_push_enabled_notifies_through_web_push_alone(): void
    {
        $event = Event::factory()->create();
        $reminder = EventReminder::factory()->create([
            'event_id' => $event->id,
            'notify_push' => true,
            'notify_sms' => false,
            'notify_email' => false,
        ]);

        $notification = new EventReminderNotification($reminder);

        $this->assertSame([WebPushChannel::class], $notification->via(User::factory()->make()));
    }

    public function test_a_reminder_with_every_channel_enabled_notifies_through_all_three(): void
    {
        $event = Event::factory()->create();
        $reminder = EventReminder::factory()->create([
            'event_id' => $event->id,
            'notify_push' => true,
            'notify_sms' => true,
            'notify_email' => true,
        ]);

        $notification = new EventReminderNotification($reminder);
        $channels = $notification->via(User::factory()->make());

        $this->assertContains(WebPushChannel::class, $channels);
        $this->assertContains('mail', $channels);
        $this->assertContains(SmsChannel::class, $channels);
    }

    public function test_reminders_fall_back_to_the_events_creator_when_no_participants_are_attached(): void
    {
        Notification::fake();

        $creator = User::factory()->create();
        $event = Event::factory()->create(['starts_at' => now()->addDay(), 'created_by' => $creator->id]);

        EventReminder::factory()->create([
            'event_id' => $event->id,
            'minutes_before' => 60 * 24,
        ]);

        $this->artisan('events:send-reminders');

        Notification::assertSentTo($creator, EventReminderNotification::class);
    }
}
