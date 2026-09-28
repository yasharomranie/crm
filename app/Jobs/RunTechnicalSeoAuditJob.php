<?php

namespace App\Jobs;

use App\Domain\Seo\TechnicalSeoAuditor;
use App\Models\SeoProject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * One project's technical crawl - kept as its own job (rather than
 * looping inside the command) so a slow/failed crawl on one project's
 * site never blocks or delays another project's audit, mirroring
 * CheckKeywordRankJob's one-job-per-subject shape.
 */
class RunTechnicalSeoAuditJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public int $seoProjectId) {}

    public function handle(TechnicalSeoAuditor $auditor): void
    {
        $auditor->audit(SeoProject::findOrFail($this->seoProjectId));
    }
}
