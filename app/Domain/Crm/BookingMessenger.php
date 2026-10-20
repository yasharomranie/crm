<?php

namespace App\Domain\Crm;

use App\Enums\BookingStatus;
use App\Enums\MessageDeliveryStatus;
use App\Models\Booking;
use App\Models\BookingMessageSetting;
use App\Models\BookingPrecheckin;
use App\Models\CustomerMessage;
use App\Models\GuestFeedback;
use Illuminate\Support\Facades\URL;

/**
 * The automatic SMS around a stay - confirmation, arrival reminder and the
 * feedback request - all written through GuestMessenger, so each one lands in
 * the guest's communication log (a failure included, with its reason). Each
 * message is sent once per booking: the sent-at timestamp on the booking
 * blocks a second one, except when staff press the button by hand ($force).
 */
class BookingMessenger
{
    public const FEEDBACK_LINK_DAYS = 60;

    public function __construct(private GuestMessenger $messenger) {}

    public function sendConfirmation(Booking $booking, bool $force = false): ?CustomerMessage
    {
        $setting = BookingMessageSetting::current();

        if (! $force && (! $setting->confirmation_enabled || $booking->confirmation_sent_at || $booking->status !== BookingStatus::Confirmed)) {
            return null;
        }

        return $this->send($booking, $setting->confirmationText(), 'confirmation_sent_at');
    }

    public function sendArrivalReminder(Booking $booking, bool $force = false): ?CustomerMessage
    {
        $setting = BookingMessageSetting::current();

        if (! $force && (! $setting->reminder_enabled || $booking->arrival_reminder_sent_at || $booking->status !== BookingStatus::Confirmed)) {
            return null;
        }

        return $this->send($booking, $setting->reminderText(), 'arrival_reminder_sent_at');
    }

    public function sendFeedbackRequest(Booking $booking, GuestFeedback $feedback, bool $force = false): ?CustomerMessage
    {
        $setting = BookingMessageSetting::current();

        if (! $force && (! $setting->feedback_enabled || $booking->feedback_sent_at)) {
            return null;
        }

        $link = URL::temporarySignedRoute('guest-feedback.show', now()->addDays(self::FEEDBACK_LINK_DAYS), ['feedback' => $feedback->id]);

        return $this->send($booking, $setting->feedbackText(), 'feedback_sent_at', $link);
    }

    /**
     * Texts every confirmed guest who arrives within the configured number of
     * days and has not been reminded yet. A booking made after the reminder
     * day began (it already got its confirmation today) is left alone.
     *
     * @return int number of reminders actually sent
     */
    public function sendDueArrivalReminders(): int
    {
        $setting = BookingMessageSetting::current();

        if (! $setting->reminder_enabled) {
            return 0;
        }

        $sent = 0;

        Booking::query()
            ->with(['guest', 'room'])
            ->where('status', BookingStatus::Confirmed)
            ->whereNull('arrival_reminder_sent_at')
            ->whereDate('check_in_date', '>=', today())
            ->whereDate('check_in_date', '<=', today()->addDays($setting->reminder_days_before))
            ->where('created_at', '<', today())
            ->each(function (Booking $booking) use (&$sent) {
                if ($this->sendArrivalReminder($booking)?->status === MessageDeliveryStatus::Sent) {
                    $sent++;
                }
            });

        return $sent;
    }

    /**
     * Fills the placeholders of a template with this booking's details.
     */
    public function render(string $template, Booking $booking, ?string $link = null): string
    {
        return strtr($template, [
            '{guest}' => (string) $booking->guest?->name,
            '{code}' => (string) $booking->reference_code,
            '{room}' => (string) $booking->room?->name,
            '{check_in}' => $booking->check_in_date->toJalali('Y/m/d'),
            '{check_out}' => $booking->check_out_date->toJalali('Y/m/d'),
            '{nights}' => (string) max(1, (int) $booking->check_in_date->diffInDays($booking->check_out_date)),
            '{link}' => (string) $link,
            '{precheckin_link}' => str_contains($template, '{precheckin_link}') ? BookingPrecheckin::forBooking($booking)->url() : '',
        ]);
    }

    private function send(Booking $booking, string $template, string $sentAtColumn, ?string $link = null): CustomerMessage
    {
        $message = $this->messenger->sendSms($booking->guest, $this->render($template, $booking, $link));

        if ($message->status === MessageDeliveryStatus::Sent) {
            $booking->forceFill([$sentAtColumn => now()])->saveQuietly();
        }

        return $message;
    }
}
