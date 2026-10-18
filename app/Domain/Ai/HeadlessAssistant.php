<?php

namespace App\Domain\Ai;

use App\Exceptions\AiProviderException;
use App\Filament\Pages\Concerns\HasAiAssistantEngine;
use App\Models\AiAssistantModelSetting;
use App\Models\AiProvider;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * The management assistant without a screen: the same system prompt, tools,
 * permissions and audit log as the chat, run on behalf of one user for a
 * single unattended question. Only read-only tools are offered - nobody is
 * there to confirm an action - and any attempt to use an action tool anyway
 * is refused.
 */
class HeadlessAssistant
{
    use HasAiAssistantEngine;

    /**
     * @return string|null the answer, or null when no provider is set up or the model said nothing
     *
     * @throws AiProviderException
     */
    public function answer(User $user, string $question): ?string
    {
        $provider = AiProvider::active();

        if (! $provider) {
            return null;
        }

        $previousUser = Auth::user();
        Auth::setUser($user);

        try {
            $excluded = [...self::CONFIRMED_ACTION_TOOLS, 'design_workflow_draft', 'design_form_template_draft'];
            $tools = array_values(array_filter(
                $this->getToolDefinitions(),
                fn (array $tool) => ! in_array($tool['function']['name'], $excluded, true),
            ));
            $model = AiAssistantModelSetting::modelFor('ai-assistant');
            $messages = [
                ['role' => 'system', 'content' => $this->systemPrompt()],
                ['role' => 'user', 'content' => $question],
            ];

            for ($round = 0; $round < 5; $round++) {
                $reply = $provider->chatCompletionWithFallback($messages, $tools, $model);
                $calls = $reply['tool_calls'] ?? null;
                $messages[] = $reply;

                if (! $calls) {
                    return trim((string) ($reply['content'] ?? '')) ?: null;
                }

                foreach ($calls as $call) {
                    $name = $call['function']['name'] ?? '';
                    $result = in_array($name, $excluded, true)
                        ? ['error' => 'این ابزار در گزارش زمان‌بندی‌شده مجاز نیست.']
                        : $this->executeTool($name, json_decode($call['function']['arguments'] ?? '{}', true) ?: []);

                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $call['id'],
                        'content' => json_encode($result, JSON_UNESCAPED_UNICODE),
                    ];
                }
            }

            $messages[] = [
                'role' => 'system',
                'content' => 'محدودیت تعداد مراحل رسیده است. دیگر ابزاری صدا نزن؛ با اطلاعاتی که تا اینجا گرفته‌ای بهترین پاسخ ممکن را بده و صریح بگو کدام بخش را کامل نکردی.',
            ];

            return trim((string) ($provider->chatCompletionWithFallback($messages, [], $model)['content'] ?? '')) ?: null;
        } finally {
            $previousUser ? Auth::setUser($previousUser) : Auth::forgetUser();
        }
    }
}
