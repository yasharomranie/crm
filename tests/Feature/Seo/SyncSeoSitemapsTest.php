<?php

namespace Tests\Feature\Seo;

use App\Jobs\SyncSitemapDataJob;
use App\Models\SeoProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class SyncSeoSitemapsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_dispatches_a_sync_job_only_for_active_projects_with_a_sitemap_registered(): void
    {
        Bus::fake();

        $eligible = SeoProject::factory()->create(['is_active' => true, 'sitemap_url' => 'https://ganjnameh.example.com/sitemap.xml']);
        $noSitemap = SeoProject::factory()->create(['is_active' => true, 'sitemap_url' => null]);
        $inactive = SeoProject::factory()->create(['is_active' => false, 'sitemap_url' => 'https://other.example.com/sitemap.xml']);

        $this->artisan('seo:sync-sitemaps')->assertSuccessful();

        Bus::assertDispatched(SyncSitemapDataJob::class, fn (SyncSitemapDataJob $job) => $job->seoProjectId === $eligible->id);
        Bus::assertNotDispatched(SyncSitemapDataJob::class, fn (SyncSitemapDataJob $job) => $job->seoProjectId === $noSitemap->id);
        Bus::assertNotDispatched(SyncSitemapDataJob::class, fn (SyncSitemapDataJob $job) => $job->seoProjectId === $inactive->id);
    }
}
