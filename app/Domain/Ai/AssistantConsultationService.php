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

    /** How many levels deeper a consulted assistant may itself ask: A asks B, B may ask C, C asks nobody. */
    private const MAX_NESTED_DEPTH = 1;

    private const MAX_NESTED_PER_CONSULTATION = 2;

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
     * Profiles $user may consult from the built-in management assistant:
     * the ones they can see that opted into being consulted.
     *
     * @return Collection<int, AiAssistantProfile>
     */
    public function consultantsForManager(User $user): Collection
    {
        return AiAssistantProfile::query()
            ->visibleTo($user)
            ->where('consultable', true)
            ->get();
    }

    /**
     * Asks $target one question. The consulted assistant answers with its own
     * persona AND its own knowledge notes, and may use the read-only CRM
     * lookups its owner granted it (CrmToolCatalog::grantedTo, narrowed by the
     * asking user's permissions) for a couple of rounds. It may itself ask one
     * further assistant (never one already in the chain, one level deep at
     * most) but cannot save knowledge or fetch pages. A mission passes its own
     * $instructions and a $depth that already rules nesting out, and may add
     * $extraTools of its own (name => description, parameters and a handler
     * callable) that are offered next to the granted lookups.
     *
     * @return array{answer: string, consulted: string, consulted_profile_id: int, tools_used: list<string>, nested: list<string>, tokens: int}|array{error: string}
     */
    public function consult(?AiAssistantProfile $requester, AiAssistantProfile $target, string $question, ?AiConversation $conversation = null, ?User $user = null, int $depth = 0, array $chain = [], ?string $instructions = null, array $extraTools = []): array
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

        foreach ($extraTools as $name => $definition) {
            $tools[] = ['type' => 'function', 'function' => ['name' => $name, 'description' => $definition['description'], 'parameters' => $definition['parameters']]];
        }

        $user ??= Auth::user();
        $chain = array_values(array_unique(array_filter([...$chain, $requester?->id, $target->id])));

        // One level of nesting, never back to anyone already in the chain,
        // so a loop (A→B→A) or a runaway chain is impossible by construction.
        $nestedCandidates = $depth < self::MAX_NESTED_DEPTH && $user
            ? $this->consultantsForManager($user)->reject(fn (AiAssistantProfile $profile) => in_array($profile->id, $chain, true))->values()
            : collect();

        if ($nestedCandidates->isNotEmpty()) {
            $tools[] = ['type' => 'function', 'function' => [
                'name' => 'consult_assistant',
                'description' => 'یک سؤال مشخص و مستقل را از دستیار تخصصی دیگری می‌پرسد. فقط وقتی پاسخ به تخصص دیگری نیاز دارد که خودت نداری.',
                'parameters' => ['type' => 'object', 'properties' => [
                    'target_profile_id' => ['type' => 'integer', 'enum' => $nestedCandidates->pluck('id')->all(), 'description' => 'شناسهٔ دستیاری که باید از او پرسید.'],
                    'question' => ['type' => 'string', 'description' => 'سؤال مشخص و مستقل.'],
                ], 'required' => ['target_profile_id', 'question']],
            ]];
        }

        $nestedLeft = self::MAX_NESTED_PER_CONSULTATION;
        $nested = [];
        $directory = $nestedCandidates->map(fn (AiAssistantProfile $profile) => '#'.$profile->id.' «'.$profile->displayName().'»'.(filled($profile->specialties) ? ' ('.$profile->specialties.')' : ''))->implode('؛ ');

        $messages = [
            ['role' => 'system', 'content' => $target->basePrompt()."\n\n".($instructions ?? 'دستیار دیگری از تو مشورت می‌خواهد. کوتاه، دقیق و کامل جواب بده.').($nestedCandidates->isNotEmpty() ? "\nاگر بخشی از جواب در تخصص تو نیست می‌توانی با consult_assistant از یکی از اینها بپرسی: {$directory}" : '')],
            ['role' => 'user', 'content' => $question],
        ];
        $toolsUsed = [];
        $tokens = 0;
        $spent = fn () => (int) ($provider->lastUsage['total_tokens'] ?? (($provider->lastUsage['prompt_tokens'] ?? 0) + ($provider->lastUsage['completion_tokens'] ?? 0)));

        try {
            $response = ['content' => null];

            for ($round = 0; $round < self::MAX_TOOL_ROUNDS; $round++) {
                $response = $provider->chatCompletion($messages, $tools, $target->model);
                $tokens += $spent();
                $calls = $response['tool_calls'] ?? null;

                if (! $calls || $tools === []) {
                    break;
                }

                $messages[] = $response;

                foreach ($calls as $call) {
                    $name = $call['function']['name'] ?? '';
                    $arguments = json_decode($call['function']['arguments'] ?? '{}', true) ?: [];

                    if ($name === 'consult_assistant' && $nestedCandidates->isNotEmpty()) {
                        $result = $this->consultNested($target, $nestedCandidates, $arguments, $conversation, $user, $depth, $chain, $nestedLeft, $nested);
                    } elseif (isset($extraTools[$name])) {
                        $result = ($extraTools[$name]['handler'])($arguments);
                    } else {
                        $result = isset($granted[$name])
                            ? $catalog->execute($name, $arguments)
                            : ['error' => 'این ابزار برای این دستیار مجاز نیست.'];
                    }

                    $toolsUsed[] = $name;

                    $messages[] = ['role' => 'tool', 'tool_call_id' => $call['id'], 'content' => json_encode($result, JSON_UNESCAPED_UNICODE)];
                }

                // Out of rounds with calls still pending: ask once more for a plain answer.
                if ($round === self::MAX_TOOL_ROUNDS - 1) {
                    $response = $provider->chatCompletion($messages, [], $target->model);
                    $tokens += $spent();
                }
            }
        } catch (AiProviderException $exception) {
            return ['error' => $exception->getMessage()];
        }

        $answer = trim((string) ($response['content'] ?? ''));

        // The built-in management assistant has no profile row (null
        // requester); its consultations are audited in the activity log.
        if ($requester) {
            AiAssistantConsultation::create([
                'consulting_ai_assistant_profile_id' => $requester->id,
                'consulted_ai_assistant_profile_id' => $target->id,
                'ai_conversation_id' => $conversation?->id,
                'question' => $question,
                'answer' => $answer,
            ]);
        }

        return ['answer' => $answer, 'consulted' => $target->displayName(), 'consulted_profile_id' => $target->id, 'tools_used' => array_values(array_unique($toolsUsed)), 'nested' => $nested, 'tokens' => $tokens];
    }

    /**
     * The consulted assistant asking a third one (depth 1 at most).
     *
     * @param  Collection<int, AiAssistantProfile>  $candidates
     * @param  array<string, mixed>  $arguments
     * @param  list<int>  $chain
     * @param  list<string>  $nested  names of the assistants it asked, filled in
     * @return array<string, mixed>
     */
    private function consultNested(AiAssistantProfile $asker, Collection $candidates, array $arguments, ?AiConversation $conversation, ?User $user, int $depth, array $chain, int &$nestedLeft, array &$nested): array
    {
        $question = trim((string) ($arguments['question'] ?? ''));
        $inner = $candidates->firstWhere('id', (int) ($arguments['target_profile_id'] ?? 0));

        if ($nestedLeft <= 0) {
            return ['error' => 'سقف تعداد مشورت در این مرحله به پایان رسیده است.'];
        }

        if (! $inner || $question === '') {
            return ['error' => 'این دستیار برای مشورت در دسترس نیست یا سؤال خالی است.'];
        }

        $nestedLeft--;
        $result = $this->consult($asker, $inner, $question, $conversation, $user, $depth + 1, $chain);

        if (isset($result['consulted'])) {
            $nested[] = $result['consulted'];
        }

        return $result;
    }
}
