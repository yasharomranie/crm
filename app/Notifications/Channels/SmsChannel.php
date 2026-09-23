<?php

namespace App\Notifications\Channels;

use App\Support\SmsGateway;
use Illuminate\Notifications\Notification;

/**
 * A minimal custom Laravel notification channel (there's no
 * laravel-notification-channels package for sms.ir, unlike WebPush) - any
 * notification wanting SMS delivery lists SmsChannel::class in via() and
 * implements toSms(), the same shape as the built-in mail/database
 * channels' toMail()/toArray().
 */
class SmsChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms')) {
            return;
        }

        $mobile = $notifiable->mobile ?? null;

        if (blank($mobile)) {
            return;
        }

        $text = $notification->toSms($notifiable);

        if (blank($text)) {
            return;
        }

        SmsGateway::send($mobile, $text);
    }
}
