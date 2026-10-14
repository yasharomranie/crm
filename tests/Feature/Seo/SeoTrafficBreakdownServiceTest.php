<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\SeoTrafficBreakdownService;
use App\Models\SeoCountryMetric;
use App\Models\SeoProject;
use App\Models\SeoSearchAppearanceMetric;
use App\Models\SeoSearchTypeMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTrafficBreakdownServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_top_countries_sums_clicks_within_the_window_and_sorts_descending(): void
    {
        $project = SeoProject::factory()->create();

        SeoCountryMetric::factory()->create(['seo_project_id' => $project->id, 'date' => now()->subDays(5), 'country' => 'irn', 'clicks' => 10]);
        SeoCountryMetric::factory()->create(['seo_project_id' => $project->id, 'date' => now()->subDays(3), 'country' => 'irn', 'clicks' => 5]);
        SeoCountryMetric::factory()->create(['seo_project_id' => $project->id, 'date' => now()->subDays(2), 'country' => 'irq', 'clicks' => 7]);
        // Outside the 28-day window - must not be counted.
        SeoCountryMetric::factory()->create(['seo_project_id' => $project->id, 'date' => now()->subDays(40), 'country' => 'irn', 'clicks' => 1000]);

        $result = app(SeoTrafficBreakdownService::class)->topCountries($project);

        $this->assertSame('IRN', $result[0]['country']);
        $this->assertSame(15, $result[0]['clicks']);
        $this->assertSame('IRQ', $result[1]['country']);
        $this->assertSame(7, $result[1]['clicks']);
    }

    public function test_top_countries_is_scoped_to_the_given_project(): void
    {
        $project = SeoProject::factory()->create();
        $otherProject = SeoProject::factory()->create();

        SeoCountryMetric::factory()->create(['seo_project_id' => $otherProject->id, 'date' => now(), 'country' => 'irn', 'clicks' => 99]);

        $this->assertCount(0, app(SeoTrafficBreakdownService::class)->topCountries($project));
    }

    public function test_by_search_type_sums_clicks_within_the_window(): void
    {
        $project = SeoProject::factory()->create();

        SeoSearchTypeMetric::factory()->create(['seo_project_id' => $project->id, 'date' => now()->subDays(1), 'search_type' => 'image', 'clicks' => 4]);
        SeoSearchTypeMetric::factory()->create(['seo_project_id' => $project->id, 'date' => now()->subDays(2), 'search_type' => 'image', 'clicks' => 6]);
        SeoSearchTypeMetric::factory()->create(['seo_project_id' => $project->id, 'date' => now()->subDays(1), 'search_type' => 'discover', 'clicks' => 20]);

        $result = app(SeoTrafficBreakdownService::class)->bySearchType($project)->keyBy('search_type');

        $this->assertSame(10, $result['image']['clicks']);
        $this->assertSame(20, $result['discover']['clicks']);
    }

    public function test_by_search_appearance_reads_only_the_latest_synced_date(): void
    {
        $project = SeoProject::factory()->create();

        SeoSearchAppearanceMetric::factory()->create(['seo_project_id' => $project->id, 'date' => now()->subDays(1), 'search_appearance' => 'RICHCARD', 'clicks' => 3]);
        SeoSearchAppearanceMetric::factory()->create(['seo_project_id' => $project->id, 'date' => now(), 'search_appearance' => 'AMP_BLUE_LINK', 'clicks' => 9]);

        $result = app(SeoTrafficBreakdownService::class)->bySearchAppearance($project);

        $this->assertCount(1, $result);
        $this->assertSame('AMP_BLUE_LINK', $result[0]['search_appearance']);
        $this->assertSame(9, $result[0]['clicks']);
    }

    public function test_an_unsynced_project_returns_empty_collections(): void
    {
        $project = SeoProject::factory()->create();
        $service = app(SeoTrafficBreakdownService::class);

        $this->assertCount(0, $service->topCountries($project));
        $this->assertCount(0, $service->bySearchType($project));
        $this->assertCount(0, $service->bySearchAppearance($project));
    }
}
