<?php

namespace App\Jobs;

use App\Domain\Ai\WatchedSourceChecker;
use App\Models\AiAssistantWatchedSource;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * One watched source's daily check. A fetch failure (dead link, timeout,
 * a URL the owner saved that's since become unreachable) is logged rather
 * than thrown - it must never crash the whole day's batch (see
 * CheckAiAssistantWatchedSources, which dispatches one of these per
 * watched source).
 */
class CheckAiAssistantWatchedSourceJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $watchedSourceId) {}

    public function handle(WatchedSourceChecker $checker): void
    {
        $source = AiAssistantWatchedSource::find($this->watchedSourceId);

        if (! $source) {
            return;
        }

        try {
            $checker->check($source);
        } catch (\Throwable $exception) {
            Log::warning('CheckAiAssistantWatchedSourceJob: check failed.', [
                'watched_source_id' => $this->watchedSourceId,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
