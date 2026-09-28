<?php

namespace App\Console\Commands;

use App\Jobs\CheckKeywordRankJob;
use App\Models\SeoTrackedKeyword;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('seo:check-tracked-keyword-ranks')]
#[Description('Dispatch a daily Google rank check for every active tracked keyword')]
class CheckTrackedKeywordRanks extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $keywords = SeoTrackedKeyword::query()->where('is_active', true)->get();

        foreach ($keywords as $keyword) {
            CheckKeywordRankJob::dispatch($keyword->id);
        }

        $this->info("Done. {$keywords->count()} tracked keyword(s) queued for a rank check.");

        return self::SUCCESS;
    }
}
