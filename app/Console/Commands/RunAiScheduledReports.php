<?php

namespace App\Console\Commands;

use App\Jobs\RunAiScheduledReportJob;
use App\Models\AiScheduledReport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('ai-assistant:run-scheduled-reports')]
#[Description('Dispatch a run for every active management-assistant report whose daily time has arrived and has not run yet today')]
class RunAiScheduledReports extends Command
{
    public function handle(): int
    {
        $now = Carbon::now('Asia/Tehran');

        $due = AiScheduledReport::query()
            ->where('is_active', true)
            ->get()
            ->filter(function (AiScheduledReport $report) use ($now) {
                $scheduledToday = $now->copy()->setTimeFromTimeString($report->run_at_time);
                $alreadyRanToday = $report->last_run_at?->copy()->timezone('Asia/Tehran')->isSameDay($now);

                return $now->greaterThanOrEqualTo($scheduledToday) && ! $alreadyRanToday;
            });

        foreach ($due as $report) {
            RunAiScheduledReportJob::dispatch($report->id);
        }

        $this->info("Done. {$due->count()} scheduled report(s) queued for a run.");

        return self::SUCCESS;
    }
}
