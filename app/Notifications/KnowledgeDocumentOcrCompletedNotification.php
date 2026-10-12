<?php

namespace App\Notifications;

use App\Models\AiAssistantProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Sent by App\Jobs\OcrKnowledgeUploadJob once a scanned PDF's background
 * OCR run finishes - the owner dispatched it from the AI Assistants Hub
 * and moved on, since OCR takes minutes; this is how they find out it's
 * done without having to keep checking the knowledge panel. WebPush
 * only, same choice App\Notifications\NewMessageReceived already made:
 * there's no in-app notification bell here to read a database entry from.
 */
class KnowledgeDocumentOcrCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly AiAssistantProfile $profile,
        private readonly int $noteCount,
        private readonly ?string $error,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, self $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('OCR فایل اسکن‌شده تمام شد')
            ->body($this->summary())
            ->icon('/icons/icon-192.png')
            ->badge('/icons/icon-192.png')
            ->dir('rtl')
            ->lang('fa')
            ->options(['TTL' => 60 * 60 * 24]);
    }

    private function summary(): string
    {
        if ($this->error !== null) {
            return "«{$this->profile->displayName()}»: {$this->error}";
        }

        if ($this->noteCount === 0) {
            return "«{$this->profile->displayName()}»: حتی با OCR هم نکتهٔ قابل‌استفاده‌ای در فایل پیدا نشد.";
        }

        return "«{$this->profile->displayName()}»: {$this->noteCount} نکته از فایل اسکن‌شده استخراج شد و در صف بررسی قرار گرفت.";
    }
}
