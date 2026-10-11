<?php

namespace App\Console\Commands;

use App\Jobs\RunAiAssistantScheduledPromptJob;
use App\Models\AiAssistantScheduledPrompt;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('ai-assistants:run-scheduled-prompts')]
#[Description('Dispatch a run for every active AI assistant scheduled prompt whose daily time has arrived and has not run yet today')]
class RunAiAssistantScheduledPrompts extends Command
{
    /**
     * Runs frequently (routes/console.php) rather than once a day at an
     * exact minute, since a scheduled prompt's run_at_time is a free-form
     * owner-chosen time, not aligned to this command's own schedule - a
     * prompt is due once "now" has reached or passed its time today and
     * it has not already run today.
     */
    public function handle(): int
    {
        $now = Carbon::now('Asia/Tehran');

        $due = AiAssistantScheduledPrompt::query()
            ->where('is_active', true)
            ->get()
            ->filter(function (AiAssistantScheduledPrompt $prompt) use ($now) {
                $scheduledToday = $now->copy()->setTimeFromTimeString($prompt->run_at_time);
                $alreadyRanToday = $prompt->last_run_at?->copy()->timezone('Asia/Tehran')->isSameDay($now);

                return $now->greaterThanOrEqualTo($scheduledToday) && ! $alreadyRanToday;
            });

        foreach ($due as $prompt) {
            RunAiAssistantScheduledPromptJob::dispatch($prompt->id);
        }

        $this->info("Done. {$due->count()} scheduled prompt(s) queued for a run.");

        return self::SUCCESS;
    }
}
