<?php

namespace App\Console\Commands;

use App\Jobs\CheckAiAssistantWatchedSourceJob;
use App\Models\AiAssistantWatchedSource;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ai-assistants:check-watched-sources')]
#[Description('Dispatch a daily change check for every AI assistant watched source')]
class CheckAiAssistantWatchedSources extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $sources = AiAssistantWatchedSource::all();

        foreach ($sources as $source) {
            CheckAiAssistantWatchedSourceJob::dispatch($source->id);
        }

        $this->info("Done. {$sources->count()} watched source(s) queued for a check.");

        return self::SUCCESS;
    }
}
