<?php

namespace App\Domain\Ai;

use App\Exceptions\AiProviderException;
use App\Models\AiAssistantConsultation;
use App\Models\AiAssistantProfile;
use App\Models\AiConversation;
use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * The inter-agent half of the knowledge-base work (alongside
 * save_knowledge_entry, fetch_url, and the document-upload path): lets
 * one custom assistant profile consult another one, by specialty,
 * mid-conversation - a bounded, auditable request/answer exchange,
 * unlike HasCustomAssistantChatEngine::forwardConversationTo()'s manual,
 * user-driven, one-way copy of a whole conversation.
 *
 * Depth is capped at exactly one level by construction, not by tracking
 * a call chain: consult() below gives the consulted profile's own
 * chatCompletion call no tools at all, so it is structurally unable to
 * consult anyone itself - no cycle or runaway chain of AI calls can ever
 * occur, and no indirect tool-execution side effect can happen either
 * (a lightweight profile consulting a powerful one never grants it that
 * power by proxy).
 */
class AssistantConsultationService
{
    /**
     * Profiles $user can see (own or shared) that have opted into being
     * consulted, excluding $profile itself - the live "directory" both
     * the system prompt and the consult_assistant tool's target enum are
     * built from.
     *
     * @return Collection<int, AiAssistantProfile>
     */
    public function availableConsultantsFor(AiAssistantProfile $profile, User $user): Collection
    {
        return AiAssistantProfile::query()
            ->visibleTo($user)
            ->where('consultable', true)
            ->whereKeyNot($profile->id)
            ->get();
    }

    /**
     * @return array{answer: string, consulted: string}|array{error: string}
     */
    public function consult(AiAssistantProfile $requester, AiAssistantProfile $target, string $question, ?AiConversation $conversation = null): array
    {
        $provider = AiProvider::active();

        if (! $provider) {
            return ['error' => AiProviderException::noActiveProvider()->getMessage()];
        }

        $messages = [
            ['role' => 'system', 'content' => $target->description ?: 'شما یک دستیار هوشمند کمکی هستید.'],
            ['role' => 'user', 'content' => $question],
        ];

        try {
            // No tools offered here - see the class docblock: this is what
            // makes the one-level depth cap structural rather than a
            // counter that could be bypassed by a bug elsewhere.
            $response = $provider->chatCompletion($messages, [], $target->model);
        } catch (AiProviderException $exception) {
            return ['error' => $exception->getMessage()];
        }

        $answer = trim((string) ($response['content'] ?? ''));

        AiAssistantConsultation::create([
            'consulting_ai_assistant_profile_id' => $requester->id,
            'consulted_ai_assistant_profile_id' => $target->id,
            'ai_conversation_id' => $conversation?->id,
            'question' => $question,
            'answer' => $answer,
        ]);

        return ['answer' => $answer, 'consulted' => $target->displayName()];
    }
}
