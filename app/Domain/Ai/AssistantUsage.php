<?php

namespace App\Domain\Ai;

use App\Models\AiMessage;
use Morilog\Jalali\Jalalian;

/**
 * What the management assistant has used this Jalali month, what that cost
 * at the configured token prices, and how close it is to the monthly cap.
 * Caps and prices live in config/ai_assistant.php; zero switches each off.
 */
class AssistantUsage
{
    /**
     * @return array{prompt: int, completion: int}
     */
    public function monthTokens(): array
    {
        $messages = AiMessage::query()
            ->where('created_at', '>=', Jalalian::now()->getFirstDayOfMonth()->toCarbon()->startOfDay());

        return [
            'prompt' => (int) (clone $messages)->sum('prompt_tokens'),
            'completion' => (int) (clone $messages)->sum('completion_tokens'),
        ];
    }

    public function costToman(int $promptTokens, int $completionTokens): int
    {
        return (int) round(
            $promptTokens / 1_000_000 * (float) config('ai_assistant.prompt_price_per_million_toman', 0)
            + $completionTokens / 1_000_000 * (float) config('ai_assistant.completion_price_per_million_toman', 0)
        );
    }

    public function monthCostToman(): int
    {
        $tokens = $this->monthTokens();

        return $this->costToman($tokens['prompt'], $tokens['completion']);
    }

    /**
     * How much of the monthly cap is used (the larger of the cost and token
     * caps), or null when no cap is set.
     */
    public function capUsagePercent(): ?int
    {
        $tokens = $this->monthTokens();
        $percents = [];

        if (($costCap = (int) config('ai_assistant.monthly_cost_cap_toman', 0)) > 0) {
            $percents[] = $this->costToman($tokens['prompt'], $tokens['completion']) / $costCap * 100;
        }

        if (($tokenCap = (int) config('ai_assistant.monthly_token_cap', 0)) > 0) {
            $percents[] = ($tokens['prompt'] + $tokens['completion']) / $tokenCap * 100;
        }

        return $percents === [] ? null : (int) floor(max($percents));
    }

    public function isOverCap(): bool
    {
        return ($this->capUsagePercent() ?? 0) >= 100;
    }

    /**
     * A sentence for the admins once usage nears the cap (80%+), else null.
     */
    public function capWarning(): ?string
    {
        $percent = $this->capUsagePercent();

        if ($percent === null || $percent < 80) {
            return null;
        }

        return $percent >= 100
            ? 'سقف مصرف ماهانهٔ دستیار هوشمند پر شده و پرسش‌های تازه تا ماه بعد (یا تغییر سقف) پاسخ داده نمی‌شود.'
            : "مصرف ماهانهٔ دستیار هوشمند به {$percent}٪ سقف رسیده است.";
    }
}
