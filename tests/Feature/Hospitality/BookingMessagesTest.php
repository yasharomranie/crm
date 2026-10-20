<?php

namespace Tests\Feature\Hospitality;

use App\Domain\Crm\BookingMessenger;
use App\Enums\BookingPaymentKind;
use App\Enums\BookingPaymentMethod;
use App\Enums\BookingStatus;
use App\Enums\MessageChannel;
use App\Enums\MessageDeliveryStatus;
use App\Enums\WebhookEventType;
use App\Filament\Pages\BookingMessageSettings;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Jobs\DispatchWebhookJob;
use App\Models\Booking;
use App\Models\BookingMessageSetting;
use App\Models\CustomerMessage;
use App\Models\ExternalApiClient;
use App\Models\Guest;
use App\Models\GuestFeedback;
use App\Models\SmsSetting;
use App\Models\User;
use App\Models\WebhookSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingMessagesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin']));

        return $admin;
    }

    private function smsReady(): void
    {
        Http::fake(['api.sms.ir/*' => Http::response(['status' => 1])]);
        SmsSetting::factory()->create();
    }

    private function setting(array $values): BookingMessageSetting
    {
        $setting = BookingMessageSetting::current();
        $setting->update($values);

        return $setting;
    }

    private function booking(array $overrides = []): Booking
    {
        return Booking::factory()->create($overrides + [
            'status' => BookingStatus::Confirmed,
            'check_in_date' => now()->addDays(5)->toDateString(),
            'check_out_date' => now()->addDays(7)->toDateString(),
        ]);
    }

    public function test_nothing_is_texted_until_the_manager_turns_it_on(): void
    {
        $this->smsReady();

        $this->booking();

        $this->assertSame(0, CustomerMessage::count());
        Http::assertNothingSent();
    }

    public function test_a_confirmed_booking_texts_the_guest_once_with_its_details(): void
    {
        $this->smsReady();
        $this->setting(['confirmation_enabled' => true]);

        $booking = $this->booking();

        $message = CustomerMessage::sole();
        $this->assertSame(MessageChannel::Sms, $message->channel);
        $this->assertSame(MessageDeliveryStatus::Sent, $message->status);
        $this->assertStringContainsString($booking->reference_code, $message->body);
        $this->assertStringContainsString($booking->guest->name, $message->body);
        $this->assertStringContainsString($booking->room->name, $message->body);
        $this->assertNotNull($booking->fresh()->confirmation_sent_at);

        $booking->update(['notes' => 'تغییر کوچک']);
        $this->assertSame(1, CustomerMessage::count());
    }

    public function test_the_room_types_check_in_and_out_times_fill_the_template(): void
    {
        $this->smsReady();
        $this->setting(['confirmation_enabled' => true, 'confirmation_template' => 'ورود {check_in_time} خروج {check_out_time}']);

        $booking = $this->booking();
        $booking->room->roomType->update(['check_in_time' => '15:30', 'check_out_time' => '11:00']);

        $this->assertSame('ورود 15:30 خروج 11:00', app(BookingMessenger::class)->render('ورود {check_in_time} خروج {check_out_time}', $booking->fresh()));
    }

    public function test_guests_who_owe_money_get_one_balance_reminder_before_arrival(): void
    {
        $this->smsReady();
        $this->setting(['balance_reminder_enabled' => true, 'balance_reminder_days_before' => 3]);

        $owing = $this->booking(['check_in_date' => today()->addDays(2)->toDateString(), 'check_out_date' => today()->addDays(4)->toDateString()]);
        $owing->charges()->create(['title' => 'اقامت', 'quantity' => 1, 'unit_amount' => 1000000]);
        $settled = $this->booking(['check_in_date' => today()->addDays(2)->toDateString(), 'check_out_date' => today()->addDays(4)->toDateString()]);
        $settled->recordPayment($settled->fresh()->balanceDue(), BookingPaymentMethod::Cash);
        $tooFar = $this->booking(['check_in_date' => today()->addDays(10)->toDateString(), 'check_out_date' => today()->addDays(12)->toDateString()]);
        CustomerMessage::query()->delete();

        $this->assertSame(0, $settled->fresh()->balanceDue());
        $this->assertSame(1, app(BookingMessenger::class)->sendDueBalanceReminders());

        $message = CustomerMessage::sole();
        $this->assertStringContainsString(number_format($owing->fresh()->balanceDue()), $message->body);
        $this->assertNotNull($owing->fresh()->balance_reminder_sent_at);
        $this->assertNull($settled->fresh()->balance_reminder_sent_at);
        $this->assertNull($tooFar->fresh()->balance_reminder_sent_at);

        $this->assertSame(0, app(BookingMessenger::class)->sendDueBalanceReminders());
    }

    public function test_nothing_is_sent_until_the_balance_reminder_is_turned_on(): void
    {
        $this->smsReady();
        $this->booking(['check_in_date' => today()->addDay()->toDateString(), 'check_out_date' => today()->addDays(3)->toDateString()]);
        CustomerMessage::query()->delete();

        $this->assertSame(0, app(BookingMessenger::class)->sendDueBalanceReminders());
        $this->assertSame(0, CustomerMessage::count());
    }

    public function test_a_pending_booking_is_texted_when_it_becomes_confirmed(): void
    {
        $this->smsReady();
        $this->setting(['confirmation_enabled' => true]);

        $booking = $this->booking(['status' => BookingStatus::Pending]);
        $this->assertSame(0, CustomerMessage::count());

        $booking->update(['status' => BookingStatus::Confirmed]);

        $this->assertSame(1, CustomerMessage::count());
    }

    public function test_a_failed_text_is_logged_and_does_not_count_as_sent(): void
    {
        $this->setting(['confirmation_enabled' => true]);

        $booking = $this->booking();

        $message = CustomerMessage::sole();
        $this->assertSame(MessageDeliveryStatus::Failed, $message->status);
        $this->assertNull($booking->fresh()->confirmation_sent_at);
    }

    public function test_the_wording_comes_from_the_template(): void
    {
        $this->smsReady();
        $this->setting(['confirmation_enabled' => true, 'confirmation_template' => 'سلام {guest}، {nights} شب، کد {code}']);

        $booking = $this->booking(['check_in_date' => now()->addDays(5)->toDateString(), 'check_out_date' => now()->addDays(8)->toDateString()]);

        $this->assertSame("سلام {$booking->guest->name}، 3 شب، کد {$booking->reference_code}", CustomerMessage::sole()->body);
    }

    public function test_arrival_reminders_go_out_once_for_guests_arriving_soon(): void
    {
        $this->smsReady();
        $this->setting(['reminder_enabled' => true, 'reminder_days_before' => 2]);

        $soon = $this->booking(['check_in_date' => now()->addDay()->toDateString(), 'check_out_date' => now()->addDays(3)->toDateString(), 'created_at' => now()->subDays(3)]);
        $far = $this->booking(['created_at' => now()->subDays(3)]);
        $madeToday = $this->booking(['check_in_date' => now()->addDay()->toDateString(), 'check_out_date' => now()->addDays(3)->toDateString()]);
        $cancelled = $this->booking(['status' => BookingStatus::Cancelled, 'check_in_date' => now()->addDay()->toDateString(), 'check_out_date' => now()->addDays(3)->toDateString(), 'created_at' => now()->subDays(3)]);

        $this->assertSame(1, app(BookingMessenger::class)->sendDueArrivalReminders());
        $this->assertSame(0, app(BookingMessenger::class)->sendDueArrivalReminders());

        $this->assertNotNull($soon->fresh()->arrival_reminder_sent_at);
        $this->assertNull($far->fresh()->arrival_reminder_sent_at);
        $this->assertNull($madeToday->fresh()->arrival_reminder_sent_at);
        $this->assertNull($cancelled->fresh()->arrival_reminder_sent_at);
    }

    public function test_reminders_stay_off_when_disabled(): void
    {
        $this->smsReady();
        $this->booking(['check_in_date' => now()->addDay()->toDateString(), 'check_out_date' => now()->addDays(3)->toDateString(), 'created_at' => now()->subDays(3)]);

        $this->assertSame(0, app(BookingMessenger::class)->sendDueArrivalReminders());
    }

    public function test_checkout_texts_a_signed_feedback_link_and_the_guest_can_answer_once(): void
    {
        $this->smsReady();
        $this->setting(['feedback_enabled' => true]);

        $booking = $this->booking();
        $booking->checkIn();
        $booking->checkOut();

        $feedback = GuestFeedback::sole();
        $this->assertNotNull($booking->fresh()->feedback_sent_at);
        $this->assertStringContainsString('/feedback/'.$feedback->id.'?', CustomerMessage::sole()->body);

        $url = URL::temporarySignedRoute('guest-feedback.show', now()->addDay(), ['feedback' => $feedback->id]);

        $this->get($url)->assertOk()->assertSee('اقامتتان چطور بود؟');

        $this->post($url, ['nps_score' => 9, 'comment' => 'عالی بود'])->assertOk()->assertSee('سپاسگزاریم');

        $feedback->refresh();
        $this->assertSame(9, $feedback->nps_score);
        $this->assertSame('عالی بود', $feedback->comment);
        $this->assertNotNull($feedback->submitted_at);

        $this->post($url, ['nps_score' => 1])->assertOk();
        $this->assertSame(9, $feedback->fresh()->nps_score);
    }

    public function test_the_feedback_link_rejects_a_tampered_url_and_a_bad_score(): void
    {
        $feedback = GuestFeedback::query()->create(['guest_id' => Guest::factory()->create()->id, 'requested_at' => now()]);

        $this->get('/feedback/'.$feedback->id)->assertForbidden();

        $url = URL::temporarySignedRoute('guest-feedback.show', now()->addDay(), ['feedback' => $feedback->id]);
        $this->post($url, ['nps_score' => 11])->assertSessionHasErrors('nps_score');
        $this->assertNull($feedback->fresh()->nps_score);
    }

    public function test_checkout_without_the_setting_only_records_the_request(): void
    {
        $this->smsReady();

        $booking = $this->booking();
        $booking->checkIn();
        $booking->checkOut();

        $this->assertSame(1, GuestFeedback::count());
        $this->assertSame(0, CustomerMessage::count());
    }

    public function test_staff_can_resend_a_message_by_hand_from_the_bookings_list(): void
    {
        $this->smsReady();
        $this->setting(['confirmation_enabled' => true]);
        $booking = $this->booking();

        Livewire::actingAs($this->admin())
            ->test(ListBookings::class)
            ->callTableAction('sendBookingSms', $booking, data: ['type' => 'confirmation'])
            ->assertNotified('پیامک ارسال شد.');

        $this->assertSame(2, CustomerMessage::count());
    }

    public function test_the_settings_page_saves_templates_and_switches(): void
    {
        Livewire::actingAs($this->admin())
            ->test(BookingMessageSettings::class)
            ->assertSet('data.confirmation_template', BookingMessageSetting::DEFAULT_CONFIRMATION)
            ->set('data.confirmation_enabled', true)
            ->set('data.reminder_enabled', true)
            ->set('data.reminder_days_before', 3)
            ->set('data.confirmation_template', 'تأیید {code}')
            ->call('save');

        $setting = BookingMessageSetting::current();
        $this->assertTrue($setting->confirmation_enabled);
        $this->assertTrue($setting->reminder_enabled);
        $this->assertSame(3, $setting->reminder_days_before);
        $this->assertSame('تأیید {code}', $setting->confirmationText());
    }

    public function test_a_user_without_permission_cannot_open_the_settings_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'بدون دسترسی پیام رزرو']));

        Livewire::actingAs($user)->test(BookingMessageSettings::class)->assertForbidden();
    }

    public function test_every_stage_of_a_booking_and_its_payments_reach_subscribed_webhooks(): void
    {
        Queue::fake();
        $client = ExternalApiClient::factory()->create();

        foreach ([WebhookEventType::BookingConfirmed, WebhookEventType::BookingCheckedIn, WebhookEventType::BookingCheckedOut, WebhookEventType::BookingCancelled, WebhookEventType::BookingPaymentRecorded] as $event) {
            WebhookSubscription::factory()->create(['external_api_client_id' => $client->id, 'event_type' => $event]);
        }

        $booking = $this->booking(['status' => BookingStatus::Pending, 'external_api_client_id' => $client->id]);
        $booking->update(['status' => BookingStatus::Confirmed]);
        $booking->recordPayment(100_000, BookingPaymentMethod::Cash, BookingPaymentKind::Deposit);
        $booking->checkIn();
        $booking->checkOut();

        $other = $this->booking(['external_api_client_id' => $client->id]);
        $other->cancel('تغییر برنامه');

        $events = [];
        Queue::assertPushed(DispatchWebhookJob::class, function (DispatchWebhookJob $job) use (&$events) {
            $events[] = $job->eventType;

            return true;
        });

        foreach ([WebhookEventType::BookingConfirmed, WebhookEventType::BookingCheckedIn, WebhookEventType::BookingCheckedOut, WebhookEventType::BookingCancelled, WebhookEventType::BookingPaymentRecorded] as $event) {
            $this->assertContains($event, $events, $event->value);
        }

        Queue::assertPushed(DispatchWebhookJob::class, fn (DispatchWebhookJob $job) => $job->eventType === WebhookEventType::BookingPaymentRecorded
            && $job->payload['amount'] === 100_000
            && $job->payload['kind'] === 'deposit');
    }
}
