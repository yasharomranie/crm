<?php

namespace App\Domain\Ai;

use App\Exceptions\AiProviderException;
use App\Models\AiAssistantConsultation;
use App\Models\AiAssistantProfile;
use App\Models\AiConversation;
use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * The inter-agent half of the knowledge-base work (alongside
 * save_knowledge_entry, fetch_url, and the document-upload path): lets
 * one custom assistant profile consult another one, by specialty,
 * mid-conversation - a bounded, auditable request/answer exchange,
 * unlike HasCustomAssistantChatEngine::forwardConversationTo()'s manual,
 * user-driven, one-way copy of a whole conversation.
 *
 * Depth is capped at exactly one level by construction, not by tracking
 * a call chain: consult() below only ever offers the consulted profile
 * its owner-granted, read-only CRM lookups - never consult_assistant,
 * save_knowledge_entry or fetch_url - so it is structurally unable to
 * consult anyone itself and no cycle or runaway chain of AI calls can
 * occur. Those lookups run with the ASKING user's permissions, so a
 * lightweight profile consulting a powerful one never grants anyone
 * anything they could not already read.
 */
class AssistantConsultationService
{
    private const MAX_TOOL_ROUNDS = 3;

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
     * Asks $target one question. The consulted assistant answers with its own
     * persona AND its own knowledge notes, and may use the read-only CRM
     * lookups its owner granted it (CrmToolCatalog::grantedTo, narrowed by the
     * asking user's permissions) for a couple of rounds. It is still offered
     * nothing else, so it cannot consult anyone, save knowledge or fetch pages.
     *
     * @return array{answer: string, consulted: string, consulted_profile_id: int, tools_used: list<string>}|array{error: string}
     */
    public function consult(AiAssistantProfile $requester, AiAssistantProfile $target, string $question, ?AiConversation $conversation = null, ?User $user = null): array
    {
        $provider = AiProvider::active();

        if (! $provider) {
            return ['error' => AiProviderException::noActiveProvider()->getMessage()];
        }

        $catalog = app(CrmToolCatalog::class);
        $granted = $catalog->grantedTo($target, $user ?? Auth::user());
        $tools = collect($granted)
            ->map(fn (array $definition, string $name) => ['type' => 'function', 'function' => ['name' => $name, 'description' => $definition['description'], 'parameters' => $definition['parameters']]])
            ->values()
            ->all();

        $messages = [
            ['role' => 'system', 'content' => $target->basePrompt()."\n\nدستیار دیگری از تو مشورت می‌خواهد. کوتاه، دقیق و کامل جواب بده."],
            ['role' => 'user', 'content' => $question],
        ];
        $toolsUsed = [];

        try {
            $response = ['content' => null];

            for ($round = 0; $round < self::MAX_TOOL_ROUNDS; $round++) {
                $response = $provider->chatCompletion($messages, $tools, $target->model);
                $calls = $response['tool_calls'] ?? null;

                if (! $calls || $tools === []) {
                    break;
                }

                $messages[] = $response;

                foreach ($calls as $call) {
                    $name = $call['function']['name'] ?? '';
                    $result = isset($granted[$name])
                        ? $catalog->execute($name, json_decode($call['function']['arguments'] ?? '{}', true) ?: [])
                        : ['error' => 'این ابزار برای این دستیار مجاز نیست.'];
                    $toolsUsed[] = $name;

                    $messages[] = ['role' => 'tool', 'tool_call_id' => $call['id'], 'content' => json_encode($result, JSON_UNESCAPED_UNICODE)];
                }

                // Out of rounds with calls still pending: ask once more for a plain answer.
                if ($round === self::MAX_TOOL_ROUNDS - 1) {
                    $response = $provider->chatCompletion($messages, [], $target->model);
                }
            }
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

        return ['answer' => $answer, 'consulted' => $target->displayName(), 'consulted_profile_id' => $target->id, 'tools_used' => array_values(array_unique($toolsUsed))];
    }
}
