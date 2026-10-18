<?php

namespace App\Jobs;

use App\Domain\Ai\ScheduledReportRunner;
use App\Models\AiScheduledReport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * One scheduled assistant report's run. A failure is logged, not thrown, so
 * one manager's broken report never stops the others from running.
 */
class RunAiScheduledReportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $reportId) {}

    public function handle(ScheduledReportRunner $runner): void
    {
        $report = AiScheduledReport::find($this->reportId);

        if (! $report) {
            return;
        }

        try {
            $runner->run($report);
        } catch (\Throwable $exception) {
            Log::warning('RunAiScheduledReportJob: run failed.', [
                'report_id' => $this->reportId,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
