<?php

namespace Tests\Feature\Seo;

use App\Jobs\RunTechnicalSeoAuditJob;
use App\Models\SeoProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class RunTechnicalSeoAuditsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_dispatches_an_audit_job_only_for_active_projects(): void
    {
        Bus::fake();

        $active = SeoProject::factory()->create(['is_active' => true]);
        $inactive = SeoProject::factory()->create(['is_active' => false]);

        $this->artisan('seo:run-technical-audits')->assertSuccessful();

        Bus::assertDispatched(RunTechnicalSeoAuditJob::class, fn (RunTechnicalSeoAuditJob $job) => $job->seoProjectId === $active->id);
        Bus::assertNotDispatched(RunTechnicalSeoAuditJob::class, fn (RunTechnicalSeoAuditJob $job) => $job->seoProjectId === $inactive->id);
    }
}
