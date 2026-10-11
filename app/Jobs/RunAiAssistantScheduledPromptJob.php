<?php

namespace App\Jobs;

use App\Domain\Ai\ScheduledPromptRunner;
use App\Models\AiAssistantScheduledPrompt;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * One scheduled prompt's daily run. A failure (provider outage, a
 * deleted profile) is logged rather than thrown - it must never crash
 * the whole batch (see RunAiAssistantScheduledPrompts, which dispatches
 * one of these per due prompt).
 */
class RunAiAssistantScheduledPromptJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $scheduledPromptId) {}

    public function handle(ScheduledPromptRunner $runner): void
    {
        $scheduledPrompt = AiAssistantScheduledPrompt::find($this->scheduledPromptId);

        if (! $scheduledPrompt) {
            return;
        }

        try {
            $runner->run($scheduledPrompt);
        } catch (\Throwable $exception) {
            Log::warning('RunAiAssistantScheduledPromptJob: run failed.', [
                'scheduled_prompt_id' => $this->scheduledPromptId,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
