<?php

namespace App\Jobs;

use App\Domain\Ai\WhatsAppInboundMessageHandler;
use App\Models\WhatsAppBusinessSetting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * One incoming WhatsApp message, handled off the webhook request itself -
 * WhatsAppWebhookController must ack Meta's POST quickly (Meta retries if
 * a webhook doesn't respond fast), so the actual chatCompletion call and
 * reply are done here.
 */
class ProcessWhatsAppInboundMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(
        public string $waId,
        public string $text,
        public ?string $contactName = null,
    ) {}

    public function handle(WhatsAppInboundMessageHandler $handler): void
    {
        try {
            $handler->handle(WhatsAppBusinessSetting::current(), $this->waId, $this->text, $this->contactName);
        } catch (\Throwable $exception) {
            Log::warning('ProcessWhatsAppInboundMessageJob: handling failed.', [
                'wa_id' => $this->waId,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
