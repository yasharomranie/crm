<?php

namespace Tests\Feature\Seo;

use App\Jobs\AnalyzeCompetitorContentGapsJob;
use App\Models\SeoProject;
use App\Models\SeoProjectCompetitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class AnalyzeCompetitorContentGapsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_dispatches_a_job_only_for_active_projects_with_at_least_one_competitor(): void
    {
        Bus::fake();

        $eligible = SeoProject::factory()->create(['is_active' => true]);
        SeoProjectCompetitor::factory()->for($eligible, 'project')->create();

        $activeWithoutCompetitors = SeoProject::factory()->create(['is_active' => true]);

        $inactive = SeoProject::factory()->create(['is_active' => false]);
        SeoProjectCompetitor::factory()->for($inactive, 'project')->create();

        $this->artisan('seo:analyze-competitor-gaps')->assertSuccessful();

        Bus::assertDispatched(AnalyzeCompetitorContentGapsJob::class, fn (AnalyzeCompetitorContentGapsJob $job) => $job->seoProjectId === $eligible->id);
        Bus::assertNotDispatched(AnalyzeCompetitorContentGapsJob::class, fn (AnalyzeCompetitorContentGapsJob $job) => $job->seoProjectId === $activeWithoutCompetitors->id);
        Bus::assertNotDispatched(AnalyzeCompetitorContentGapsJob::class, fn (AnalyzeCompetitorContentGapsJob $job) => $job->seoProjectId === $inactive->id);
    }
}
