<?php

namespace App\Domain\Ai;

use App\Enums\AiMessageRole;
use App\Models\AiMessage;

/**
 * Tool results carry personal and financial data (who was late, whose wallet
 * holds what). They are only needed while a conversation is fresh, so older
 * ones are blanked - the tool message itself stays, because the model's
 * earlier tool call must keep a matching result.
 */
class AssistantRetention
{
    public const PRUNED_NOTE = '{"note":"خروجی این ابزار طبق سیاست نگهداری حذف شد."}';

    /**
     * @return int how many tool results were blanked
     */
    public function pruneToolOutput(?int $days = null): int
    {
        $days ??= (int) config('ai_assistant.tool_output_retention_days', 90);

        if ($days <= 0) {
            return 0;
        }

        return AiMessage::query()
            ->where('role', AiMessageRole::Tool)
            ->where('created_at', '<', now()->subDays($days))
            ->where('content', '!=', self::PRUNED_NOTE)
            ->update(['content' => self::PRUNED_NOTE]);
    }
}
