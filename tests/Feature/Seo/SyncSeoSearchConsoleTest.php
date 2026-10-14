<?php

namespace Tests\Feature\Seo;

use App\Enums\SeoConnectionStatus;
use App\Jobs\SyncSearchConsoleDataJob;
use App\Models\SeoGoogleConnection;
use App\Models\SeoProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class SyncSeoSearchConsoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_dispatches_a_sync_job_only_for_active_projects_with_a_connected_gsc_property(): void
    {
        Bus::fake();

        $eligible = SeoProject::factory()->create(['is_active' => true]);
        SeoGoogleConnection::factory()->for($eligible, 'project')->create();

        $inactive = SeoProject::factory()->create(['is_active' => false]);
        SeoGoogleConnection::factory()->for($inactive, 'project')->create();

        $notConnected = SeoProject::factory()->create(['is_active' => true]);
        SeoGoogleConnection::factory()->for($notConnected, 'project')->create(['status' => SeoConnectionStatus::Error]);

        $noPropertyPicked = SeoProject::factory()->create(['is_active' => true]);
        SeoGoogleConnection::factory()->for($noPropertyPicked, 'project')->create(['gsc_site_url' => null]);

        $noConnectionAtAll = SeoProject::factory()->create(['is_active' => true]);

        $this->artisan('seo:sync-search-console')->assertSuccessful();

        Bus::assertDispatched(SyncSearchConsoleDataJob::class, fn (SyncSearchConsoleDataJob $job) => $job->seoProjectId === $eligible->id);
        Bus::assertNotDispatched(SyncSearchConsoleDataJob::class, fn (SyncSearchConsoleDataJob $job) => $job->seoProjectId === $inactive->id);
        Bus::assertNotDispatched(SyncSearchConsoleDataJob::class, fn (SyncSearchConsoleDataJob $job) => $job->seoProjectId === $notConnected->id);
        Bus::assertNotDispatched(SyncSearchConsoleDataJob::class, fn (SyncSearchConsoleDataJob $job) => $job->seoProjectId === $noPropertyPicked->id);
        Bus::assertNotDispatched(SyncSearchConsoleDataJob::class, fn (SyncSearchConsoleDataJob $job) => $job->seoProjectId === $noConnectionAtAll->id);
    }
}
