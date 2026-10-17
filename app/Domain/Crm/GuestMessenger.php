<?php

namespace App\Domain\Crm;

use App\Enums\MessageChannel;
use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageDirection;
use App\Mail\GuestEmail;
use App\Models\CustomerMessage;
use App\Models\Guest;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Support\SmsGateway;
use Illuminate\Support\Facades\Mail;

/**
 * Sends an email or SMS to a guest from the CRM and keeps every
 * conversation in one place (customer_messages), so staff can see what has
 * been said to a customer on any channel. A send that can't go out - the
 * mail driver is still the "log" placeholder, SMS isn't configured, the SMTP
 * server rejects it - is recorded as failed with the reason, never
 * silently reported as sent.
 */
class GuestMessenger
{
    public function sendEmail(Guest $guest, string $subject, string $body, ?User $sender = null): CustomerMessage
    {
        $error = null;

        if (blank($guest->email)) {
            $error = 'این مشتری ایمیل ثبت‌شده‌ای ندارد.';
        } elseif (in_array(config('mail.default'), ['log', 'array'], true)) {
            $error = 'ارسال ایمیل روی این سرور تنظیم نشده است (MAIL_MAILER هنوز روی log است).';
        } else {
            try {
                Mail::to($guest->email)->send(new GuestEmail($subject, $body));
            } catch (\Throwable $exception) {
                $error = 'ارسال ایمیل ناموفق بود: '.$exception->getMessage();
            }
        }

        return $this->record($guest, MessageChannel::Email, $body, $error, $sender, $subject);
    }

    public function sendSms(Guest $guest, string $body, ?User $sender = null): CustomerMessage
    {
        $error = null;

        if (blank($guest->mobile)) {
            $error = 'این مشتری شماره‌ی موبایل ثبت‌شده‌ای ندارد.';
        } elseif (! SmsGateway::send($guest->mobile, $body)) {
            $error = 'ارسال پیامک ناموفق بود (سرویس پیامک غیرفعال، تنظیم‌نشده یا دارای خطاست).';
        }

        return $this->record($guest, MessageChannel::Sms, $body, $error, $sender);
    }

    /**
     * Copies a WhatsApp message into the guest's communication log when its
     * contact's number matches a guest. WhatsApp contacts that aren't a
     * guest yet are skipped - there's no one to attach the history to.
     */
    public function recordWhatsAppMessage(WhatsAppMessage $message): ?CustomerMessage
    {
        $waId = (string) $message->conversation?->wa_id;
        $normalized = GuestDuplicateDetector::normalizeMobile($waId);

        $guest = Guest::query()
            ->where(fn ($query) => $query
                ->where('mobile', $waId)
                ->orWhere('mobile', '+'.$waId)
                ->when($normalized, fn ($query) => $query->orWhere('mobile', $normalized)))
            ->first();

        if (! $guest) {
            return null;
        }

        return CustomerMessage::create([
            'guest_id' => $guest->id,
            'channel' => MessageChannel::WhatsApp,
            'direction' => $message->direction === 'inbound' ? MessageDirection::Inbound : MessageDirection::Outbound,
            'body' => $message->content,
            'status' => MessageDeliveryStatus::Sent,
        ]);
    }

    private function record(Guest $guest, MessageChannel $channel, string $body, ?string $error, ?User $sender, ?string $subject = null): CustomerMessage
    {
        $message = CustomerMessage::create([
            'guest_id' => $guest->id,
            'channel' => $channel,
            'direction' => MessageDirection::Outbound,
            'subject' => $subject,
            'body' => $body,
            'status' => $error ? MessageDeliveryStatus::Failed : MessageDeliveryStatus::Sent,
            'error' => $error,
            'sent_by' => $sender?->id,
        ]);

        activity('crm_interactions')
            ->performedOn($guest)
            ->causedBy($sender)
            ->withProperties(['customer_message_id' => $message->id])
            ->event($channel->value.'_sent')
            ->log(($error ? 'تلاش ناموفق برای ارسال ' : 'ارسال ').$channel->getLabel().($subject ? ": {$subject}" : ''));

        return $message;
    }
}
