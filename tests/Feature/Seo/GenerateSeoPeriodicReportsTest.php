<?php

namespace Tests\Feature\Seo;

use App\Models\SeoProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateSeoPeriodicReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_a_report_only_for_active_projects(): void
    {
        $active = SeoProject::factory()->create(['is_active' => true]);
        $inactive = SeoProject::factory()->create(['is_active' => false]);

        $this->artisan('seo:generate-periodic-reports')->assertSuccessful();

        $this->assertSame(1, $active->periodicReports()->count());
        $this->assertSame(0, $inactive->periodicReports()->count());
    }

    public function test_the_generated_period_is_last_calendar_month(): void
    {
        $project = SeoProject::factory()->create(['is_active' => true]);

        $this->artisan('seo:generate-periodic-reports')->assertSuccessful();

        $report = $project->periodicReports()->firstOrFail();
        $expectedStart = now()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $expectedEnd = now()->subMonthNoOverflow()->endOfMonth()->toDateString();

        $this->assertSame($expectedStart, $report->period_start->toDateString());
        $this->assertSame($expectedEnd, $report->period_end->toDateString());
    }
}
