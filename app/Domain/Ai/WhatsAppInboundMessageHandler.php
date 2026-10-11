<?php

namespace App\Domain\Ai;

use App\Exceptions\AiProviderException;
use App\Models\AiProvider;
use App\Models\WhatsAppBusinessSetting;
use App\Models\WhatsAppConversation;
use Illuminate\Support\Facades\Log;

/**
 * Turns one incoming WhatsApp text message into a reply, sent back over
 * the same WhatsApp thread - the guest-facing counterpart to
 * HasCustomAssistantChatEngine's internal-panel chat. No tools are ever
 * offered here (the same zero-tool sandboxing ScheduledPromptRunner and
 * AssistantConsultationService::consult() use), and deliberately so: this
 * channel is unattended and open to anyone who can message the configured
 * WhatsApp Business number, so it must never be able to call fetch_url,
 * consult_assistant, or any granted CRM tool - only answer from its own
 * system prompt and approved knowledge, exactly like a scheduled prompt
 * run.
 */
class WhatsAppInboundMessageHandler
{
    /** Recent turns kept as context - enough for a short back-and-forth without the prompt growing unbounded. */
    private const HISTORY_LIMIT = 20;

    public function __construct(private readonly WhatsAppCloudApiClient $client) {}

    public function handle(WhatsAppBusinessSetting $setting, string $waId, string $text, ?string $contactName = null): void
    {
        if (! $setting->isUsable()) {
            return;
        }

        $profile = $setting->profile;

        if (! $profile) {
            return;
        }

        $conversation = WhatsAppConversation::query()->firstOrCreate(
            ['ai_assistant_profile_id' => $profile->id, 'wa_id' => $waId],
            ['contact_name' => $contactName],
        );

        if ($contactName && blank($conversation->contact_name)) {
            $conversation->update(['contact_name' => $contactName]);
        }

        $conversation->messages()->create(['direction' => 'inbound', 'content' => $text]);

        $provider = AiProvider::active();

        if (! $provider) {
            return;
        }

        $systemPrompt = $profile->description ?: 'شما یک دستیار هوشمند کمکی هستید.';
        $knowledge = $profile->approvedKnowledgeForPrompt();

        if ($knowledge !== []) {
            $systemPrompt .= "\n\nدانش انباشته‌شدهٔ این دستیار - در پاسخ‌هایت از این نکات استفاده کن:\n- ".implode("\n- ", $knowledge);
        }

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ...$conversation->messages()->get()->map(fn ($message) => [
                'role' => $message->direction === 'inbound' ? 'user' : 'assistant',
                'content' => $message->content,
            ])->take(-self::HISTORY_LIMIT)->values()->all(),
        ];

        try {
            $response = $provider->chatCompletion($messages, [], $profile->model);
        } catch (AiProviderException $exception) {
            Log::warning('WhatsAppInboundMessageHandler: provider call failed.', [
                'wa_id' => $waId,
                'exception' => $exception->getMessage(),
            ]);

            return;
        }

        $reply = trim((string) ($response['content'] ?? ''));

        if ($reply === '') {
            return;
        }

        $conversation->messages()->create(['direction' => 'outbound', 'content' => $reply]);

        $this->client->sendTextMessage($setting->phone_number_id, $setting->access_token, $waId, $reply);
    }
}
