<?php

namespace App\Domain\Ai;

use App\Exceptions\AiProviderException;
use App\Models\AiAssistantScheduledPrompt;
use App\Models\AiProvider;

/**
 * Runs one AiAssistantScheduledPrompt unattended - the general-purpose
 * counterpart to WatchedSourceChecker, which only ever diffs a URL.
 * Exactly one chatCompletion call, with no tools offered at all (the same
 * zero-tool sandboxing AssistantConsultationService::consult() uses):
 * this is a standing instruction a human isn't present to supervise, so
 * it must never be able to call fetch_url, consult_assistant, or act on
 * granted CRM tools - only read the profile's own system prompt and
 * knowledge, and hand back a note for the owner to review like anything
 * else save_knowledge_entry would produce.
 */
class ScheduledPromptRunner
{
    public function run(AiAssistantScheduledPrompt $scheduledPrompt): void
    {
        $profile = $scheduledPrompt->profile;

        if (! $profile) {
            return;
        }

        $provider = AiProvider::active();

        if (! $provider) {
            $scheduledPrompt->forceFill(['last_run_at' => now()])->save();

            return;
        }

        $systemPrompt = $profile->description ?: 'شما یک دستیار هوشمند کمکی هستید.';
        $knowledge = $profile->approvedKnowledgeForPrompt();

        if ($knowledge !== []) {
            $systemPrompt .= "\n\nدانش انباشته‌شدهٔ این دستیار از گفتگوهای قبلی - در پاسخ‌هایت از این نکات استفاده کن:\n- ".implode("\n- ", $knowledge);
        }

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $scheduledPrompt->prompt],
        ];

        try {
            $response = $provider->chatCompletion($messages, [], $profile->model);
        } catch (AiProviderException) {
            $scheduledPrompt->forceFill(['last_run_at' => now()])->save();

            return;
        }

        $content = trim((string) ($response['content'] ?? ''));

        if ($content !== '') {
            $profile->recordAiSuggestedKnowledge("[اجرای خودکار روزانه - «{$scheduledPrompt->prompt}»]\n{$content}");
        }

        $scheduledPrompt->forceFill(['last_run_at' => now()])->save();
    }
}
