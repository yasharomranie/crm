<?php

namespace Tests\Feature\Crm;

use App\Domain\Crm\GuestMessenger;
use App\Enums\MessageChannel;
use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageDirection;
use App\Mail\GuestEmail;
use App\Models\CustomerMessage;
use App\Models\Guest;
use App\Models\SmsSetting;
use App\Models\User;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class GuestMessengerTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_email_is_sent_and_logged_when_a_real_mailer_is_configured(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();

        $sender = User::factory()->create();
        $guest = Guest::factory()->create(['email' => 'sara@example.com']);

        $message = app(GuestMessenger::class)->sendEmail($guest, 'پیش‌فاکتور', 'پیش‌فاکتور پیوست است.', $sender);

        Mail::assertSent(GuestEmail::class, fn (GuestEmail $mail) => $mail->hasTo('sara@example.com') && $mail->emailSubject === 'پیش‌فاکتور');
        $this->assertSame(MessageDeliveryStatus::Sent, $message->status);
        $this->assertSame(MessageChannel::Email, $message->channel);
        $this->assertSame(MessageDirection::Outbound, $message->direction);
        $this->assertSame($sender->id, $message->sent_by);
        $this->assertSame(1, $guest->activitiesAsSubject()->where('event', 'email_sent')->count());
    }

    public function test_an_email_is_recorded_as_failed_not_sent_while_the_mail_driver_is_still_the_log_placeholder(): void
    {
        config(['mail.default' => 'log']);
        Mail::fake();

        $guest = Guest::factory()->create(['email' => 'sara@example.com']);

        $message = app(GuestMessenger::class)->sendEmail($guest, 'موضوع', 'متن');

        Mail::assertNothingSent();
        $this->assertSame(MessageDeliveryStatus::Failed, $message->status);
        $this->assertStringContainsString('MAIL_MAILER', $message->error);
    }

    public function test_an_email_to_a_guest_without_an_address_fails_with_a_reason(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();

        $guest = Guest::factory()->create(['email' => null]);

        $message = app(GuestMessenger::class)->sendEmail($guest, 'موضوع', 'متن');

        Mail::assertNothingSent();
        $this->assertSame(MessageDeliveryStatus::Failed, $message->status);
        $this->assertNotNull($message->error);
    }

    public function test_an_sms_is_sent_through_the_gateway_and_logged(): void
    {
        SmsSetting::current()->update(['is_enabled' => true, 'api_key' => 'key', 'line_number' => '3000']);
        Http::fake(['api.sms.ir/*' => Http::response(['status' => 1], 200)]);

        $guest = Guest::factory()->create(['mobile' => '09121234567']);

        $message = app(GuestMessenger::class)->sendSms($guest, 'سلام');

        Http::assertSent(fn ($request) => $request['mobiles'] === ['09121234567'] && $request['messageText'] === 'سلام');
        $this->assertSame(MessageDeliveryStatus::Sent, $message->status);
        $this->assertSame(MessageChannel::Sms, $message->channel);
    }

    public function test_an_sms_fails_with_a_reason_when_the_gateway_is_not_configured(): void
    {
        Http::fake();

        $guest = Guest::factory()->create(['mobile' => '09121234567']);

        $message = app(GuestMessenger::class)->sendSms($guest, 'سلام');

        Http::assertNothingSent();
        $this->assertSame(MessageDeliveryStatus::Failed, $message->status);
    }

    public function test_a_whatsapp_message_from_a_known_guest_is_copied_into_their_log(): void
    {
        $guest = Guest::factory()->create(['mobile' => '09121234567']);
        $conversation = WhatsAppConversation::factory()->create(['wa_id' => '989121234567']);

        WhatsAppMessage::factory()->create([
            'whats_app_conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'content' => 'سلام، قیمت تور چنده؟',
        ]);

        $logged = CustomerMessage::where('guest_id', $guest->id)->firstOrFail();

        $this->assertSame(MessageChannel::WhatsApp, $logged->channel);
        $this->assertSame(MessageDirection::Inbound, $logged->direction);
        $this->assertSame('سلام، قیمت تور چنده؟', $logged->body);
    }

    public function test_a_whatsapp_message_from_an_unknown_contact_is_ignored_without_error(): void
    {
        $conversation = WhatsAppConversation::factory()->create(['wa_id' => '989350000000']);

        WhatsAppMessage::factory()->create(['whats_app_conversation_id' => $conversation->id, 'direction' => 'outbound']);

        $this->assertSame(0, CustomerMessage::count());
    }
}
