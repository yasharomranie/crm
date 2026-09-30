<?php

namespace Tests\Feature\Seo;

use App\Domain\Seo\UrlInspectionService;
use App\Exceptions\SeoGoogleException;
use App\Models\SeoGoogleConnection;
use App\Models\SeoProject;
use App\Models\SeoUrlInspection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UrlInspectionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_the_inspection_result_for_a_page(): void
    {
        $project = SeoProject::factory()->create();
        SeoGoogleConnection::factory()->create([
            'seo_project_id' => $project->id,
            'access_token' => 'valid-token',
            'token_expires_at' => now()->addHour(),
            'gsc_site_url' => 'https://ganjnameh.example.com/',
        ]);

        Http::fake([
            'searchconsole.googleapis.com/*' => Http::response([
                'inspectionResult' => [
                    'indexStatusResult' => [
                        'verdict' => 'FAIL',
                        'robotsTxtState' => 'DISALLOWED',
                        'indexingState' => 'INDEXING_ALLOWED',
                        'pageFetchState' => 'SUCCESSFUL',
                        'coverageState' => 'Blocked by robots.txt',
                        'lastCrawlTime' => '2026-09-20T10:00:00Z',
                    ],
                ],
            ]),
        ]);

        $inspection = app(UrlInspectionService::class)->inspect($project, 'https://ganjnameh.example.com/telecabin');

        $this->assertSame('FAIL', $inspection->verdict);
        $this->assertSame('DISALLOWED', $inspection->robots_txt_state);
        $this->assertSame(1, SeoUrlInspection::where('seo_project_id', $project->id)->count());
    }

    public function test_re_inspecting_the_same_url_updates_instead_of_duplicating(): void
    {
        $project = SeoProject::factory()->create();
        SeoGoogleConnection::factory()->create([
            'seo_project_id' => $project->id,
            'access_token' => 'valid-token',
            'token_expires_at' => now()->addHour(),
            'gsc_site_url' => 'https://ganjnameh.example.com/',
        ]);

        Http::fake([
            'searchconsole.googleapis.com/*' => Http::sequence()
                ->push(['inspectionResult' => ['indexStatusResult' => ['verdict' => 'FAIL']]])
                ->push(['inspectionResult' => ['indexStatusResult' => ['verdict' => 'PASS']]]),
        ]);

        $service = app(UrlInspectionService::class);
        $service->inspect($project, 'https://ganjnameh.example.com/telecabin');
        $second = $service->inspect($project, 'https://ganjnameh.example.com/telecabin');

        $this->assertSame(1, SeoUrlInspection::where('seo_project_id', $project->id)->count());
        $this->assertSame('PASS', $second->verdict);
    }

    public function test_it_throws_when_the_project_has_no_google_connection(): void
    {
        $project = SeoProject::factory()->create();

        $this->expectException(SeoGoogleException::class);

        app(UrlInspectionService::class)->inspect($project, 'https://ganjnameh.example.com/telecabin');
    }
}
